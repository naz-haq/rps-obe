<?php

namespace App\Services\Generator;

use App\Models\BahanKajian;
use App\Models\Cpl;
use App\Models\CplBahanKajian;
use App\Models\Cpmk;
use App\Models\DokumenChunk;
use App\Models\GenerateSession;
use App\Models\Indikator;
use App\Models\KomponenPenilaian;
use App\Models\MataKuliah;
use App\Models\MkBahanKajian;
use App\Models\MkCpl;
use App\Models\MkDokumenRujukan;
use App\Models\ProfilLulusan;
use App\Models\Referensi;
use App\Models\RpsMinggu;
use App\Models\RpsVersion;
use App\Models\Rubrik;
use App\Models\RubrikKriteria;
use App\Models\SubCpmk;
use App\Models\Taksonomi;
use App\Services\Ai\AiOutcome;
use App\Services\Ai\AiService;
use App\Services\Ai\EmbeddingService;
use App\Services\Ai\GroundingValidator;
use App\Services\Ai\PromptRepository;
use App\Services\Generator\Exceptions\GeneratorException;
use App\Services\Generator\Exceptions\RevisiConflictException;
use App\Services\Rps\EstimasiWaktuService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orkestrator generate RPS BERTAHAP (Blueprint 7.4).
 *
 * Aturan keras yang ditegakkan:
 *  - Satu panggilan AI per tahap (bukan satu mega-prompt seluruh RPS).
 *  - Tahap berikutnya hanya berjalan setelah prasyaratnya (context_from)
 *    berstatus terkunci (accepted/edited/pinned).
 *  - Keluaran tiap tahap disimpan di GENERATE_SESSION.draf; status per bagian
 *    di status_bagian; bagian yang dikunci tak tertimpa saat regenerasi parsial.
 */
class RpsGeneratorService
{
    public function __construct(
        private AiService $ai,
        private GroundingValidator $grounding,
        private PromptRepository $prompts,
        private EstimasiWaktuService $estimasi,
        private EmbeddingService $embeddings,
    ) {}

    /** Kunci array item di dalam draf per tahap. */
    private const ITEM_KEY = [
        'cpmk'      => 'cpmk',
        'sub_cpmk'  => 'sub_cpmk',
        'mingguan'  => 'minggu',
        'penilaian' => 'komponen',
    ];

    /**
     * Mulai sesi penyusunan untuk satu mata kuliah.
     */
    public function start(MataKuliah $mk, array $opts = []): GenerateSession
    {
        $pipeline = config('generator.pipeline');

        return GenerateSession::create([
            'institusi_id'  => $mk->institusi_id,
            'mk_id'         => $mk->id,
            'sumber'        => $opts['sumber'] ?? 'baru',
            'tahap'         => $pipeline[0],
            'draf'          => [],
            'status_bagian' => array_fill_keys($pipeline, 'pending'),
            'status'        => 'berjalan',
            'user_id'       => $opts['user_id'] ?? null,
            'konteks_tambahan' => $opts['konteks_tambahan'] ?? null,
        ]);
    }

    /**
     * Generate satu tahap. Menegakkan urutan & kunci sebelum memanggil AI, lalu
     * memvalidasi keluaran ke DOKUMEN_CHUNK (grounding, Blueprint 7.5). Klaim yang
     * tak-grounded memicu regenerasi otomatis (auto_revisi_maks) memakai konteks
     * pengganti; bila masih bermasalah, tahap ditandai perlu_review.
     */
    public function generateStage(GenerateSession $session, string $stage): AiOutcome
    {
        $stageCfg = $this->stageConfig($stage);
        $this->assertPrerequisites($session, $stage, $stageCfg);
        $this->assertNotLocked($session, $stage);

        $mk = $session->mataKuliah;
        if (! $mk) {
            throw new GeneratorException('Sesi generate tidak terkait mata kuliah.');
        }

        // Prasyarat matriks: penurunan CPL->CPMK menuntut MK sudah dipetakan ke
        // minimal satu CPL (matriks CPL x MK). Tanpa itu keterunutan kehilangan
        // jangkar dan capaian bisa melebar ke CPL yang tak relevan.
        if (($stageCfg['jenis_output'] ?? '') === 'cpmk') {
            $this->pastikanMatriksCplSiap($mk);
        }

        $maks = $this->autoRevisiMaks();
        $koreksi = [];   // konteks pengganti terkumpul lintas percobaan
        // Regenerate tahap yang SUDAH punya draf → pengguna ingin hasil segar,
        // jadi lewati cache prompt (generate pertama tetap boleh dari cache).
        $reGenerate = isset(($session->draf ?? [])[$stage]);
        $outcome = null;
        $data = [];
        $validasi = null;

        for ($percobaan = 0; $percobaan <= $maks; $percobaan++) {
            $outcome = $this->runGenerate($session, $stage, $stageCfg, $mk, $koreksi, $reGenerate);
            $data = $this->parseJson($outcome->text(), $stage);

            // Normalisasi kode CPL keluaran AI ke kode kanonik kurikulum
            // (model kerap meniru format contoh skema, mis. "CPL01" vs "CPL-01").
            if (($stageCfg['jenis_output'] ?? '') === 'cpmk') {
                $data = $this->normalisasiCplKode($mk, $data);
            }

            // Jaring pengaman cakupan "tidak bertuan": tiap tahap punya rantai
            // keterunutan wajib (CPL→CPMK→Sub-CPMK→mingguan/penilaian). Model
            // tertentu (mis. gpt-4o) melewatkan entitas — deteksi deterministik
            // lalu ulangi dengan instruksi koreksi eksplisit.
            if ($percobaan < $maks) {
                $bertuan = $this->entitasTakBertuan($stage, $session, $data, $mk);
                if (($bertuan['hilang'] ?? []) !== []) {
                    $koreksi[] = $bertuan['pesan'];
                    continue;
                }
            }

            $validasi = $this->validateStage($session, $stage, $data, $outcome);

            // Selesai bila validasi dilewati/bersih, atau jatah revisi habis.
            if ($validasi === null || ($validasi['bersih'] ?? true) || $percobaan >= $maks) {
                break;
            }

            // Tak ada konteks pengganti untuk diinjeksikan → hentikan, tandai review.
            $konteksBaru = $validasi['konteks'] ?? [];
            if ($konteksBaru === []) {
                break;
            }
            $koreksi = array_values(array_unique(array_merge($koreksi, $konteksBaru)));
        }

        $draf = $session->draf ?? [];
        $draf[$stage] = $this->assignItemIds($stage, $data);
        $status = $session->status_bagian ?? [];
        $status[$stage] = 'draft';

        $update = [
            'draf'          => $draf,
            'status_bagian' => $status,
            'tahap'         => $stage,
        ];

        if ($validasi !== null) {
            $catatan = $session->catatan_validasi ?? [];
            $catatan[$stage] = $this->ringkasValidasi($validasi);
            $update['catatan_validasi'] = $catatan;
        }

        $session->update($update);

        return $outcome;
    }

    /**
     * Satu percobaan generate (buildPrompt + panggilan AI). Blok KOREKSI opsional
     * menyuntikkan konteks pengganti dari hasil grounding percobaan sebelumnya.
     */
    private function runGenerate(GenerateSession $session, string $stage, array $stageCfg, MataKuliah $mk, array $koreksi, bool $bypassCache = false): AiOutcome
    {
        [$system, $prompt] = $this->buildPrompt($session, $stage, $stageCfg, $mk, $koreksi);

        $outcome = $this->ai->run('generate', $system, $prompt, [
            'institusi_id' => $session->institusi_id,
            'user_id'      => $session->user_id,
            'entity_type'  => 'GenerateSession',
            'entity_id'    => $session->id,
            'mode'         => $koreksi === [] ? "generate:{$stage}" : "generate:{$stage}:revisi",
            // Anggaran token per-tahap (mis. 'mingguan' butuh lebih besar).
            // null => pakai default task 'generate'.
            'max_tokens'   => $stageCfg['max_tokens'] ?? null,
            // Regenerate manual / revisi grounding → jangan ambil dari cache.
            'no_cache'     => $bypassCache || $koreksi !== [],
        ]);

        if ($outcome->failed()) {
            throw new GeneratorException("Panggilan AI gagal pada tahap '{$stage}': " . ($outcome->result->error ?? 'tidak diketahui'));
        }

        return $outcome;
    }

    /**
     * Validasi grounding keluaran satu tahap terhadap DOKUMEN_CHUNK tenant.
     * Mengembalikan null bila grounding nonaktif/tak terpasang, tak ada klaim,
     * atau tenant tak punya dokumen rujukan (tak ada yang bisa dijadikan bukti).
     *
     * @return array{bersih:bool,konteks:array<int,string>,lolos:bool,ditolak:array,hasil:array,jumlah_klaim:int,dilewati?:string}|null
     */
    private function validateStage(GenerateSession $session, string $stage, array $data, AiOutcome $outcome): ?array
    {
        if (! config('generator.grounding.enabled', true)) {
            return null;
        }

        $interaksiId = $outcome->interaksi?->id;
        if (! $interaksiId) {
            return null; // tak ada anchor AI_INTERAKSI (mis. log dimatikan)
        }

        $adaBukti = DokumenChunk::whereNotNull('embedding')
            ->whereHas('dokumen', fn($q) => $q->where('institusi_id', $session->institusi_id)->where('sumber_konten', true))
            ->exists();
        if (! $adaBukti) {
            return ['bersih' => true, 'konteks' => [], 'lolos' => true, 'ditolak' => [], 'hasil' => [], 'jumlah_klaim' => 0, 'dilewati' => 'tak ada dokumen rujukan keilmuan (sumber_konten)'];
        }

        $klaim = $this->klaimDariDraf($stage, $data);
        if ($klaim === []) {
            return null;
        }

        $hasil = $this->grounding->validate('', [
            'institusi_id'    => $session->institusi_id,
            'ai_interaksi_id' => $interaksiId,
            'user_id'         => $session->user_id,
            'klaim'           => $klaim,
        ]);

        $konteks = [];
        $bersih = true;
        foreach ($hasil['hasil'] as $item) {
            if (($item['tindakan'] ?? 'terima') !== 'terima') {
                $bersih = false;
                if (! empty($item['konteks'])) {
                    $konteks[] = (string) $item['konteks'];
                }
            }
        }

        return [
            'bersih'       => $bersih,
            'konteks'      => $konteks,
            'lolos'        => $hasil['lolos'],
            'ditolak'      => $hasil['ditolak'],
            'hasil'        => $hasil['hasil'],
            'jumlah_klaim' => count($klaim),
        ];
    }

    /**
     * Klaim atomik (deskripsi substantif) dari draf tahap untuk divalidasi.
     *
     * @return array<int,array{teks:string,kategori:string}>
     */
    private function klaimDariDraf(string $stage, array $data): array
    {
        $peta = [
            'cpmk'     => ['cpmk', 'deskripsi'],
            'sub_cpmk' => ['sub_cpmk', 'deskripsi'],
            'mingguan' => ['minggu', 'materi_pustaka'],
            'penilaian' => ['komponen', 'nama'],
        ];
        if (! isset($peta[$stage])) {
            return [];
        }
        [$key, $field] = $peta[$stage];

        $klaim = [];
        foreach ($data[$key] ?? [] as $item) {
            $teks = trim((string) ($item[$field] ?? ''));
            if ($teks !== '') {
                $klaim[] = ['teks' => $teks, 'kategori' => 'umum'];
            }
        }

        return $klaim;
    }

    /** Ringkasan validasi untuk disimpan di GENERATE_SESSION.catatan_validasi. */
    private function ringkasValidasi(array $validasi): array
    {
        return [
            'bersih'       => $validasi['bersih'] ?? true,
            'lolos'        => $validasi['lolos'] ?? true,
            'perlu_review' => ! ($validasi['bersih'] ?? true),
            'jumlah_klaim' => $validasi['jumlah_klaim'] ?? 0,
            'jumlah_ditolak' => count($validasi['ditolak'] ?? []),
            'dilewati'     => $validasi['dilewati'] ?? null,
        ];
    }

    private function autoRevisiMaks(): int
    {
        return max(0, (int) config('ai.grounding.auto_revisi_maks', 1));
    }

    /**
     * Setujui tahap (opsional dengan hasil suntingan manusia) & majukan tahap aktif.
     */
    public function acceptStage(GenerateSession $session, string $stage, ?array $edited = null): GenerateSession
    {
        $this->stageConfig($stage);
        $status = $session->status_bagian ?? [];

        $draf = $session->draf ?? [];
        if ($edited !== null) {
            // Penyimpanan manual/suntingan: diperbolehkan meski tahap belum
            // pernah di-generate AI (pengguna mengisi sendiri kolom).
            $draf[$stage] = $this->assignItemIds($stage, $edited);
            $status[$stage] = 'edited';
        } else {
            if (($status[$stage] ?? 'pending') === 'pending') {
                throw new GeneratorException("Tahap '{$stage}' belum di-generate, tak bisa disetujui.");
            }
            $status[$stage] = 'accepted';
        }

        $next = $this->nextPendingStage($status);

        $session->update([
            'draf'          => $draf,
            'status_bagian' => $status,
            'tahap'         => $next ?? $stage,
            'status'        => $this->allLocked($status) ? 'selesai' : 'berjalan',
        ]);

        return $session->refresh();
    }

    /**
     * Tolak tahap: kembalikan ke pending & buang draf tahap tsb.
     */
    public function rejectStage(GenerateSession $session, string $stage): GenerateSession
    {
        $this->stageConfig($stage);
        $this->assertNotLocked($session, $stage);

        $draf = $session->draf ?? [];
        unset($draf[$stage]);
        $status = $session->status_bagian ?? [];
        $status[$stage] = 'pending';

        $session->update(['draf' => $draf, 'status_bagian' => $status, 'status' => 'berjalan']);

        return $session->refresh();
    }

    /**
     * Kunci tahap agar tidak tertimpa saat regenerasi parsial tahap lain.
     */
    public function pinStage(GenerateSession $session, string $stage): GenerateSession
    {
        $this->stageConfig($stage);
        $status = $session->status_bagian ?? [];

        if (($status[$stage] ?? 'pending') === 'pending') {
            throw new GeneratorException("Tahap '{$stage}' belum ada isinya untuk dikunci.");
        }

        $status[$stage] = 'pinned';
        $session->update(['status_bagian' => $status]);

        return $session->refresh();
    }

    // ---------------------------------------------------------------------
    // Regenerasi / perbaikan PER ITEM (candidate patch — audit §7)
    // ---------------------------------------------------------------------

    /**
     * Susun USULAN perbaikan untuk SATU item (tanpa menyentuh draf). AI hanya
     * memperbaiki item terpilih; item lain tetap. Balikan = kandidat berisi
     * before/after + biaya, untuk ditinjau (diff) lalu diterapkan terpisah.
     *
     * @param array{action?:string,instruction?:string} $opts
     */
    public function regenerateItem(GenerateSession $session, string $stage, string $itemId, array $opts = []): array
    {
        $stageCfg = $this->stageConfig($stage);
        $mk = $session->mataKuliah;
        if (! $mk) {
            throw new GeneratorException('Sesi generate tidak terkait mata kuliah.');
        }

        $this->backfillItemIds($session);
        [,, $item] = $this->locateItem($session, $stage, $itemId);

        if (($item['_pin'] ?? false) === true) {
            throw new GeneratorException('Item ini disematkan (pin). Lepas sematan sebelum meminta perbaikan AI.');
        }

        [$system, $prompt] = $this->buildItemPrompt($session, $stage, $stageCfg, $mk, $item, $opts);

        $outcome = $this->ai->run('generate', $system, $prompt, [
            'institusi_id' => $session->institusi_id,
            'user_id'      => $session->user_id,
            'entity_type'  => 'GenerateSession',
            'entity_id'    => $session->id,
            'mode'         => "revisi_item:{$stage}",
            'max_tokens'   => $stageCfg['max_tokens'] ?? null,
            'no_cache'     => true,
        ]);

        if ($outcome->failed()) {
            throw new GeneratorException('Panggilan AI gagal: ' . ($outcome->result->error ?? 'tidak diketahui'));
        }

        $data = $this->parseJson($outcome->text(), $stage);
        $key = self::ITEM_KEY[$stage];
        $baru = $this->ekstrakItemTunggal($data, $key);
        if (! is_array($baru)) {
            throw new GeneratorException('AI tidak mengembalikan item yang valid.');
        }

        $after = $this->normalisasiItemBaru($stage, $item, $baru);

        return [
            'stage'       => $stage,
            'item_id'     => $itemId,
            'before'      => $this->tanpaMeta($item),
            'after'       => $after,
            'base_revisi' => (int) ($session->revisi ?? 0),
            'usage'       => [
                'model'         => $outcome->interaksi?->model,
                'provider'      => $outcome->interaksi?->provider,
                'estimated_usd' => round($outcome->biaya, 6),
            ],
        ];
    }

    /**
     * Terapkan usulan satu item ke draf (optimistic locking). Item lain tetap.
     * Menaikkan revisi & menandai item hilir yang terpengaruh "perlu tinjau".
     */
    public function applyItem(GenerateSession $session, string $stage, string $itemId, array $after, int $baseRevisi): GenerateSession
    {
        $this->stageConfig($stage);
        $this->backfillItemIds($session);

        if ((int) ($session->revisi ?? 0) !== $baseRevisi) {
            throw new RevisiConflictException('Draf sudah berubah sejak usulan dibuat. Tinjau ulang perbedaan terbaru sebelum menerapkan.');
        }

        [$key, $index, $item] = $this->locateItem($session, $stage, $itemId);
        if (($item['_pin'] ?? false) === true) {
            throw new GeneratorException('Item disematkan (pin) — lepas sematan sebelum menerapkan perubahan.');
        }

        $after = $this->tanpaMeta($after);
        $after['_id'] = $item['_id'];
        if (isset($item['_pin'])) {
            $after['_pin'] = $item['_pin'];
        }

        $draf = $session->draf ?? [];
        $draf[$stage][$key][$index] = $after;
        $draf = $this->tandaiHilirPerluTinjau($draf, $stage, $after);

        $status = $session->status_bagian ?? [];
        if (($status[$stage] ?? 'pending') === 'pending') {
            $status[$stage] = 'edited';
        }

        $session->update([
            'draf'          => $draf,
            'status_bagian' => $status,
            'revisi'        => (int) ($session->revisi ?? 0) + 1,
        ]);

        return $session->refresh();
    }

    /** Sematkan/lepas sematan satu item (item tersemat tak diubah AI/apply). */
    public function setItemPin(GenerateSession $session, string $stage, string $itemId, bool $pinned): GenerateSession
    {
        $this->stageConfig($stage);
        $this->backfillItemIds($session);
        [$key, $index] = $this->locateItem($session, $stage, $itemId);

        $draf = $session->draf ?? [];
        if ($pinned) {
            $draf[$stage][$key][$index]['_pin'] = true;
        } else {
            unset($draf[$stage][$key][$index]['_pin']);
        }
        $session->update(['draf' => $draf]);

        return $session->refresh();
    }

    /** Assign _id stabil ke tiap item tahap yang belum punya (non-destruktif). */
    private function assignItemIds(string $stage, array $data): array
    {
        $key = self::ITEM_KEY[$stage] ?? null;
        if ($key === null || ! isset($data[$key]) || ! is_array($data[$key])) {
            return $data;
        }
        foreach ($data[$key] as $i => $item) {
            if (is_array($item) && empty($item['_id'])) {
                $data[$key][$i]['_id'] = (string) Str::ulid();
            }
        }

        return $data;
    }

    /** Pastikan seluruh item pada draf punya _id; simpan bila ada yang ditambah. */
    private function backfillItemIds(GenerateSession $session): void
    {
        $draf = $session->draf ?? [];
        $before = json_encode($draf);
        foreach (array_keys(self::ITEM_KEY) as $stage) {
            if (isset($draf[$stage]) && is_array($draf[$stage])) {
                $draf[$stage] = $this->assignItemIds($stage, $draf[$stage]);
            }
        }
        if (json_encode($draf) !== $before) {
            $session->update(['draf' => $draf]);
            $session->refresh();
        }
    }

    /**
     * Temukan item berdasarkan _id. Balikan [key, index, item].
     *
     * @return array{0:string,1:int,2:array}
     */
    private function locateItem(GenerateSession $session, string $stage, string $itemId): array
    {
        $key = self::ITEM_KEY[$stage] ?? null;
        if ($key === null) {
            throw new GeneratorException("Tahap '{$stage}' tak mendukung perbaikan per item.");
        }
        foreach (($session->draf[$stage][$key] ?? []) as $i => $item) {
            if (is_array($item) && ($item['_id'] ?? null) === $itemId) {
                return [$key, $i, $item];
            }
        }
        throw new GeneratorException('Item tidak ditemukan pada tahap ini.');
    }

    /** Buang kunci meta internal (_id/_pin/_needs_review) untuk pratinjau diff. */
    private function tanpaMeta(array $item): array
    {
        unset($item['_id'], $item['_pin'], $item['_needs_review']);

        return $item;
    }

    /**
     * Ekstrak SATU item dari keluaran AI, toleran terhadap variasi bentuk:
     * {"key":[item]}, {"key":item}, atau item telanjang {"kode":...}.
     */
    private function ekstrakItemTunggal(array $data, string $key): ?array
    {
        $node = $data[$key] ?? null;
        if (is_array($node)) {
            if (array_is_list($node)) {
                return isset($node[0]) && is_array($node[0]) ? $node[0] : null;
            }
            return $node; // objek langsung di bawah key
        }
        // Item telanjang di level atas (tanpa pembungkus key).
        foreach (['kode', 'minggu_ke', 'nama', 'deskripsi', 'sub_cpmk_kode'] as $petunjuk) {
            if (array_key_exists($petunjuk, $data)) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Susun item baru dari keluaran AI: pertahankan kunci identitas item lama
     * (kode/minggu_ke/keterunutan), timpa sisanya dari AI.
     */
    private function normalisasiItemBaru(string $stage, array $lama, array $baru): array
    {
        $baru = $this->tanpaMeta($baru);
        foreach (['kode', 'cpmk_kode', 'minggu_ke', 'sub_cpmk_kode'] as $idKey) {
            if (array_key_exists($idKey, $lama)) {
                $baru[$idKey] = $lama[$idKey];
            }
        }

        return $baru;
    }

    /**
     * Tandai item hilir yang bergantung pada item yang baru diubah agar ditinjau
     * (TIDAK diubah otomatis) — dependency graph CPMK→Sub-CPMK→mingguan/penilaian.
     */
    private function tandaiHilirPerluTinjau(array $draf, string $stage, array $item): array
    {
        $tandai = function (array $items, callable $cocok): array {
            foreach ($items as $i => $it) {
                if (is_array($it) && $cocok($it)) {
                    $items[$i]['_needs_review'] = true;
                }
            }
            return $items;
        };

        if ($stage === 'cpmk') {
            $kode = $item['kode'] ?? null;
            if ($kode !== null && isset($draf['sub_cpmk']['sub_cpmk'])) {
                $draf['sub_cpmk']['sub_cpmk'] = $tandai($draf['sub_cpmk']['sub_cpmk'], fn($it) => ($it['cpmk_kode'] ?? null) === $kode);
            }
        } elseif ($stage === 'sub_cpmk') {
            $kode = $item['kode'] ?? null;
            if ($kode !== null) {
                foreach (['mingguan' => 'minggu', 'penilaian' => 'komponen'] as $st => $k) {
                    if (isset($draf[$st][$k])) {
                        $draf[$st][$k] = $tandai($draf[$st][$k], fn($it) => ($it['sub_cpmk_kode'] ?? null) === $kode);
                    }
                }
            }
        }

        return $draf;
    }

    /**
     * Prompt perbaikan SATU item: konteks tahap penuh + arahan fokus item +
     * isi item saat ini + instruksi/aksi pengguna. Minta AI balik HANYA item itu.
     *
     * @param array{action?:string,instruction?:string} $opts
     * @return array{0:string,1:string}
     */
    private function buildItemPrompt(GenerateSession $session, string $stage, array $stageCfg, MataKuliah $mk, array $item, array $opts): array
    {
        [$system, $base] = $this->buildPrompt($session, $stage, $stageCfg, $mk);

        $aksiTeks = [
            'perbaiki_redaksi'    => 'Perbaiki kejelasan & keterukuran redaksi tanpa mengubah makna dan pemetaan.',
            'buat_alternatif'     => 'Tawarkan rumusan alternatif yang lebih kuat namun setara maknanya.',
            'naikkan_taksonomi'   => 'Naikkan level kognitif (taksonomi) satu tingkat bila layak; sesuaikan kata kerja operasionalnya.',
            'periksa_konsistensi' => 'Perbaiki konsistensi dengan CPL/CPMK induk & bahan kajian; luruskan bila menyimpang.',
            'perkaya'             => 'Perkaya substansi agar lebih bernilai (indikator lebih tajam/terukur) tanpa keluar dari skop.',
        ];
        $aksi = $opts['action'] ?? null;
        $instruksi = trim((string) ($opts['instruction'] ?? ''));

        $key = self::ITEM_KEY[$stage];
        $arahan = [
            "\n===================",
            'MODE PERBAIKAN SATU ITEM (WAJIB DIPATUHI, UTAMAKAN ARAHAN INI):',
            '- ABAIKAN perintah membuat banyak item di atas. Fokus HANYA memperbaiki SATU item di bawah.',
            "- Kembalikan HANYA JSON {\"{$key}\": [ <satu item hasil perbaikan> ]} — TEPAT satu item.",
            '- PERTAHANKAN nilai kunci identitas (kode/minggu_ke) & keterunutan (cpl_kode/cpmk_kode/sub_cpmk_kode) apa adanya.',
            '- Jangan menyentuh item lain; jaga konsistensi dengan konteks (CPL/CPMK/bahan kajian) di atas.',
        ];
        if ($aksi && isset($aksiTeks[$aksi])) {
            $arahan[] = '- Jenis perbaikan: ' . $aksiTeks[$aksi];
        }
        if ($instruksi !== '') {
            $arahan[] = '- Instruksi tambahan dari dosen: ' . $instruksi;
        }
        $arahan[] = 'ITEM SAAT INI:';
        $arahan[] = json_encode($this->tanpaMeta($item), JSON_UNESCAPED_UNICODE);

        return [$system, $base . "\n" . implode("\n", $arahan)];
    }

    public function readyToCommit(GenerateSession $session): bool
    {
        return $this->allLocked($session->status_bagian ?? []);
    }

    /**
     * Commit draf sesi ke entitas RPS resmi (menuntut semua tahap terkunci).
     * Menulis CPMK(+pivot CPL), Sub-CPMK(+Indikator), RPS_VERSION, RPS_MINGGU,
     * dan KOMPONEN_PENILAIAN dalam satu transaksi lalu tandai sesi 'committed'.
     */
    public function commit(GenerateSession $session): RpsVersion
    {
        if ($session->status === 'committed' || $session->rps_version_id) {
            throw new GeneratorException('Sesi sudah pernah di-commit.');
        }

        if (! $this->readyToCommit($session)) {
            throw new GeneratorException('Semua tahap harus disetujui sebelum commit.');
        }

        $mk = $session->mataKuliah;
        if (! $mk) {
            throw new GeneratorException('Sesi generate tidak terkait mata kuliah.');
        }

        $draf = $session->draf ?? [];

        return DB::transaction(function () use ($session, $mk, $draf) {
            $cpmkMap = $this->commitCpmk($session, $mk, $draf['cpmk']['cpmk'] ?? []);
            $subMap  = $this->commitSubCpmk($session, $cpmkMap, $draf['sub_cpmk']['sub_cpmk'] ?? []);

            $rps = RpsVersion::create([
                'institusi_id' => $session->institusi_id,
                'kode_mk'      => $mk->kode_mk,
                'versi'        => $this->nextRpsVersi($session->institusi_id, $mk->kode_mk),
                'status'       => 'draft',
                'bahasa'       => 'id',
                'created_by'   => $session->user_id,
            ]);

            $this->commitMinggu($rps, $subMap, $draf['mingguan']['minggu'] ?? [], $mk);
            $this->commitKomponen($rps, $subMap, $draf['penilaian']['komponen'] ?? []);

            $session->update(['rps_version_id' => $rps->id, 'status' => 'committed']);

            return $rps;
        });
    }

    // ----------------------------------------------------------------------
    // Internal
    // ----------------------------------------------------------------------

    /** @return array<string,Cpmk> kode CPMK => model */
    private function commitCpmk(GenerateSession $session, MataKuliah $mk, array $items): array
    {
        $map = [];
        foreach ($items as $item) {
            $kodeList = $this->normalizeTaksonomiKode($item['taksonomi_kode'] ?? null);
            $cpmk = Cpmk::updateOrCreate(
                [
                    'institusi_id' => $session->institusi_id,
                    'kode_mk'      => $mk->kode_mk,
                    'kode'         => $item['kode'] ?? '',
                ],
                [
                    'deskripsi'      => $item['deskripsi'] ?? '',
                    'bobot_persen'   => $item['bobot_persen'] ?? null,
                    'taksonomi_id'   => $this->findTaksonomiId($session->institusi_id, $kodeList[0] ?? null),
                    'taksonomi_kode' => $kodeList ?: null,
                ]
            );

            $cplSync = [];
            foreach ($item['cpl_kode'] ?? [] as $cplKode) {
                $cpl = $this->findCpl($mk, (string) $cplKode);
                if ($cpl) {
                    $cplSync[$cpl->id] = ['institusi_id' => $session->institusi_id];
                }
            }
            $cpmk->cpl()->sync($cplSync);

            if (($item['kode'] ?? '') !== '') {
                $map[$item['kode']] = $cpmk;
            }
        }

        return $map;
    }

    /**
     * @param  array<string,Cpmk>  $cpmkMap
     * @return array<string,SubCpmk>
     */
    private function commitSubCpmk(GenerateSession $session, array $cpmkMap, array $items): array
    {
        $map = [];
        foreach ($items as $item) {
            $cpmk = $cpmkMap[$item['cpmk_kode'] ?? ''] ?? null;
            if (! $cpmk) {
                continue; // sub-CPMK tanpa induk CPMK valid dilewati
            }

            $subKodeList = $this->normalizeTaksonomiKode($item['taksonomi_kode'] ?? null);
            $sub = SubCpmk::updateOrCreate(
                [
                    'institusi_id' => $session->institusi_id,
                    'cpmk_id'      => $cpmk->id,
                    'kode'         => $item['kode'] ?? '',
                ],
                [
                    'deskripsi'      => $item['deskripsi'] ?? '',
                    'bobot_persen'   => $item['bobot_persen'] ?? null,
                    'taksonomi_id'   => $this->findTaksonomiId($session->institusi_id, $subKodeList[0] ?? null),
                    'taksonomi_kode' => $subKodeList ?: null,
                ]
            );

            // Segarkan indikator: hapus lama lalu tulis ulang agar tidak menumpuk.
            $sub->indikator()->delete();
            foreach ($item['indikator'] ?? [] as $teks) {
                if (trim((string) $teks) !== '') {
                    Indikator::create([
                        'institusi_id' => $session->institusi_id,
                        'sub_cpmk_id'  => $sub->id,
                        'deskripsi'    => $teks,
                    ]);
                }
            }

            if (($item['kode'] ?? '') !== '') {
                $map[$item['kode']] = $sub;
            }
        }

        return $map;
    }

    /** @param array<string,SubCpmk> $subMap */
    private function commitMinggu(RpsVersion $rps, array $subMap, array $items, MataKuliah $mk): void
    {
        // Estimasi waktu = DETERMINISTIK dari SKS (Blueprint 7b); nilai draf diabaikan.
        $estimasi = $this->estimasi->untukMataKuliah($mk);

        foreach ($items as $item) {
            $sub = $subMap[$item['sub_cpmk_kode'] ?? ''] ?? null;
            RpsMinggu::create([
                'rps_version_id'            => $rps->id,
                'minggu_ke'               => $item['minggu_ke'] ?? 0,
                'sub_cpmk_id'             => $sub?->id,
                'indikator'               => $item['indikator'] ?? null,
                'teknik_kriteria_penilaian' => $item['kriteria_penilaian'] ?? null,
                'metode_pembelajaran'     => $item['metode_pembelajaran'] ?? null,
                'bentuk_luring'           => $item['bentuk_luring'] ?? null,
                'bentuk_daring'           => $item['bentuk_daring'] ?? null,
                'pengalaman_belajar'      => $item['pengalaman_belajar'] ?? null,
                'materi_pustaka'          => $item['materi_pustaka'] ?? ($item['bahan_kajian'] ?? null),
                'estimasi_waktu'          => $estimasi,
                'bobot_penilaian'         => $this->numOrNull($item['bobot_penilaian'] ?? null),
            ]);
        }
    }

    /** @param array<string,SubCpmk> $subMap */
    private function commitKomponen(RpsVersion $rps, array $subMap, array $items): void
    {
        foreach ($items as $item) {
            $sub = $subMap[$item['sub_cpmk_kode'] ?? ''] ?? null;
            $komponen = KomponenPenilaian::create([
                'rps_version_id' => $rps->id,
                'sub_cpmk_id'    => $sub?->id,
                'nama'           => $item['nama'] ?? '',
                'jenis'          => $item['jenis'] ?? 'tugas',
                'instrumen'      => $item['instrumen'] ?? null,
                'bobot_persen'   => $item['bobot_persen'] ?? null,
                'minggu_ke'      => $item['minggu_ke'] ?? null,
            ]);

            $this->commitRubrik($komponen, $item['rubrik'] ?? null);
        }
    }

    /**
     * Simpan rubrik analitik + kriteria untuk sebuah komponen penilaian.
     * Diabaikan bila draf tak menyertakan rubrik (mis. komponen objektif murni)
     * atau tak ada kriteria yang valid.
     */
    private function commitRubrik(KomponenPenilaian $komponen, mixed $rubrik): void
    {
        if (! is_array($rubrik)) {
            return;
        }

        $kriteria = array_values(array_filter(
            $rubrik['kriteria'] ?? [],
            fn($k) => is_array($k) && trim((string) ($k['kriteria'] ?? '')) !== '',
        ));
        if ($kriteria === []) {
            return;
        }

        $label = is_array($rubrik['label_skala'] ?? null) ? array_values($rubrik['label_skala']) : null;
        $level = (int) ($rubrik['jumlah_level_skala'] ?? ($label ? count($label) : 4));
        $level = $level > 0 ? $level : 4;

        $model = Rubrik::create([
            'komponen_penilaian_id' => $komponen->id,
            'jenis'                 => $rubrik['jenis'] ?? 'analitik',
            'jumlah_level_skala'    => $level,
            'label_skala'           => $label,
        ]);

        foreach ($kriteria as $urutan => $k) {
            $deskriptor = is_array($k['deskriptor'] ?? null) ? array_values($k['deskriptor']) : null;
            RubrikKriteria::create([
                'rubrik_id'  => $model->id,
                'kriteria'   => trim((string) $k['kriteria']),
                'bobot'      => $this->numOrNull($k['bobot'] ?? null),
                'deskriptor' => $deskriptor,
                'urutan'     => $urutan + 1,
            ]);
        }
    }

    private function findCpl(MataKuliah $mk, string $kode): ?Cpl
    {
        if (! $mk->kurikulum_id) {
            return null;
        }

        $cpl = Cpl::where('kurikulum_id', $mk->kurikulum_id)->where('kode', $kode)->first();
        if ($cpl) {
            return $cpl;
        }

        // Fallback toleran format: cocokkan bentuk kanonik (abaikan kapital,
        // tanda hubung/spasi) agar "CPL01" tetap ketemu "CPL-01" saat commit.
        $target = $this->kodeKanonik($kode);
        if ($target === '') {
            return null;
        }

        return Cpl::where('kurikulum_id', $mk->kurikulum_id)->get()
            ->first(fn($c) => $this->kodeKanonik((string) $c->kode) === $target);
    }

    /** Bentuk kanonik kode utk pencocokan longgar: uppercase, hanya alfanumerik. */
    private function kodeKanonik(string $kode): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $kode));
    }

    /**
     * Ganti cpl_kode pada draf CPMK dengan kode kanonik kurikulum bila cocok
     * secara bentuk kanonik (unik). Kode yang tak dikenal dibiarkan apa adanya
     * agar tetap tertangkap peringatan di editor.
     */
    private function normalisasiCplKode(MataKuliah $mk, array $data): array
    {
        if (! $mk->kurikulum_id || empty($data['cpmk']) || ! is_array($data['cpmk'])) {
            return $data;
        }

        $peta = [];   // kanonik => kode asli DB
        foreach (Cpl::where('kurikulum_id', $mk->kurikulum_id)->pluck('kode') as $kode) {
            $k = $this->kodeKanonik((string) $kode);
            // Tabrakan kanonik (ambigu) → jangan dipetakan.
            $peta[$k] = array_key_exists($k, $peta) ? null : (string) $kode;
        }

        foreach ($data['cpmk'] as $i => $item) {
            if (empty($item['cpl_kode']) || ! is_array($item['cpl_kode'])) {
                continue;
            }
            $data['cpmk'][$i]['cpl_kode'] = array_values(array_unique(array_map(
                function ($kode) use ($peta) {
                    $canon = $this->kodeKanonik((string) $kode);
                    return $peta[$canon] ?? (string) $kode;
                },
                $item['cpl_kode']
            )));
        }

        return $data;
    }

    /**
     * Deteksi entitas "tidak bertuan" per tahap sesuai rantai keterunutan OBE:
     * CPL→CPMK (tiap CPL terpetakan MK punya ≥1 CPMK), CPMK→Sub-CPMK,
     * Sub-CPMK→mingguan, dan Sub-CPMK→penilaian. Perbandingan kode memakai
     * bentuk kanonik agar tahan beda format.
     *
     * @return array{hilang:list<string>,pesan:string}
     */
    private function entitasTakBertuan(string $stage, GenerateSession $session, array $data, MataKuliah $mk): array
    {
        $draf = $session->draf ?? [];

        $sisa = function (array $harus, array $baris, string $field): array {
            foreach ($baris as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $nilai = $item[$field] ?? null;
                foreach (is_array($nilai) ? $nilai : [$nilai] as $k) {
                    unset($harus[$this->kodeKanonik((string) $k)]);
                }
            }
            return array_values($harus);
        };

        $kunci = function (array $items, string $field): array {
            $out = [];
            foreach ($items as $item) {
                $kode = trim((string) ($item[$field] ?? ''));
                if ($kode !== '') {
                    $out[$this->kodeKanonik($kode)] = $kode;
                }
            }
            return $out;
        };

        switch ($stage) {
            case 'cpmk':
                $harus = $kunci($this->cplContext($mk), 'kode');
                if ($harus === []) {
                    return ['hilang' => [], 'pesan' => ''];
                }
                $hilang = $sisa($harus, $data['cpmk'] ?? [], 'cpl_kode');
                return ['hilang' => $hilang, 'pesan' => $hilang === [] ? '' :
                    'Setiap CPL pada "CPL TERKAIT" WAJIB diturunkan menjadi minimal satu CPMK (cantumkan kodenya pada cpl_kode). '
                    . 'CPL berikut belum punya CPMK: ' . implode(', ', $hilang) . '. Tambah/sesuaikan CPMK agar SEMUA CPL tercakup.'];

            case 'sub_cpmk':
                $harus = $kunci($draf['cpmk']['cpmk'] ?? [], 'kode');
                if ($harus === []) {
                    return ['hilang' => [], 'pesan' => ''];
                }
                $hilang = $sisa($harus, $data['sub_cpmk'] ?? [], 'cpmk_kode');
                return ['hilang' => $hilang, 'pesan' => $hilang === [] ? '' :
                    'Setiap CPMK WAJIB diuraikan menjadi minimal satu Sub-CPMK. '
                    . 'CPMK berikut belum punya Sub-CPMK: ' . implode(', ', $hilang) . '.'];

            case 'mingguan':
                $hilang = $this->subCpmkTakTercakup($session, $data);
                return ['hilang' => $hilang, 'pesan' => $hilang === [] ? '' :
                    'Rencana mingguan WAJIB mencakup SEMUA Sub-CPMK; belum muncul: ' . implode(', ', $hilang)
                    . '. Bila jumlah pekan terbatas, buat BEBERAPA BARIS dengan minggu_ke yang sama (satu baris per Sub-CPMK).'];

            case 'penilaian':
                $harus = $kunci($draf['sub_cpmk']['sub_cpmk'] ?? [], 'kode');
                if ($harus === []) {
                    return ['hilang' => [], 'pesan' => ''];
                }
                $hilang = $sisa($harus, $data['komponen'] ?? [], 'sub_cpmk_kode');
                return ['hilang' => $hilang, 'pesan' => $hilang === [] ? '' :
                    'Setiap Sub-CPMK WAJIB diukur oleh minimal satu komponen penilaian. '
                    . 'Sub-CPMK berikut belum dinilai: ' . implode(', ', $hilang) . '.'];
        }

        return ['hilang' => [], 'pesan' => ''];
    }

    /**
     * Daftar kode Sub-CPMK (dari draf tahap sub_cpmk sesi) yang TIDAK muncul
     * pada draf rencana mingguan. Kosong = semua tercakup / tak bisa dicek.
     *
     * @return list<string>
     */
    private function subCpmkTakTercakup(GenerateSession $session, array $data): array
    {
        $harus = [];
        foreach (($session->draf ?? [])['sub_cpmk']['sub_cpmk'] ?? [] as $item) {
            $kode = trim((string) ($item['kode'] ?? ''));
            if ($kode !== '') {
                $harus[$this->kodeKanonik($kode)] = $kode;
            }
        }
        if ($harus === []) {
            return [];
        }

        foreach ($data['minggu'] ?? [] as $baris) {
            unset($harus[$this->kodeKanonik((string) ($baris['sub_cpmk_kode'] ?? ''))]);
        }

        return array_values($harus);
    }

    private function findTaksonomiId(int $institusiId, ?string $kode): ?int
    {
        if (! $kode) {
            return null;
        }

        return Taksonomi::where('kode', $kode)
            ->where(fn($q) => $q->where('institusi_id', $institusiId)->orWhereNull('institusi_id'))
            ->orderByRaw('institusi_id IS NULL')
            ->value('id');
    }

    /**
     * Normalisasi taksonomi_kode dari draf (string tunggal atau array) menjadi
     * daftar kode unik & bersih.
     *
     * @return list<string>
     */
    private function normalizeTaksonomiKode(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $items = is_array($raw) ? $raw : [$raw];
        $out = [];
        foreach ($items as $k) {
            $k = trim((string) $k);
            if ($k !== '' && ! in_array($k, $out, true)) {
                $out[] = $k;
            }
        }
        return $out;
    }

    private function numOrNull(mixed $v): ?float
    {
        return ($v === null || $v === '') ? null : (float) $v;
    }

    private function nextRpsVersi(int $institusiId, string $kodeMk): int
    {
        return (int) RpsVersion::where('institusi_id', $institusiId)
            ->where('kode_mk', $kodeMk)
            ->max('versi') + 1;
    }

    private function stageConfig(string $stage): array
    {
        $cfg = config("generator.stages.{$stage}");
        if (! $cfg) {
            throw new GeneratorException("Tahap generator tidak dikenal: {$stage}");
        }

        return $cfg;
    }

    private function assertPrerequisites(GenerateSession $session, string $stage, array $stageCfg): void
    {
        $status = $session->status_bagian ?? [];
        $locked = config('generator.locked_states');

        foreach ($stageCfg['context_from'] as $dep) {
            if (! in_array($status[$dep] ?? 'pending', $locked, true)) {
                throw new GeneratorException(
                    "Tahap '{$stage}' butuh tahap '{$dep}' disetujui lebih dulu (aturan generate bertahap)."
                );
            }
        }
    }

    private function assertNotLocked(GenerateSession $session, string $stage): void
    {
        $status = $session->status_bagian ?? [];
        if (($status[$stage] ?? 'pending') === 'pinned') {
            throw new GeneratorException("Tahap '{$stage}' terkunci (pinned); lepas kunci sebelum regenerasi.");
        }
    }

    private function nextPendingStage(array $status): ?string
    {
        foreach (config('generator.pipeline') as $stage) {
            if (($status[$stage] ?? 'pending') === 'pending') {
                return $stage;
            }
        }

        return null;
    }

    private function allLocked(array $status): bool
    {
        $locked = config('generator.locked_states');
        foreach (config('generator.pipeline') as $stage) {
            if (! in_array($status[$stage] ?? 'pending', $locked, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int,string>  $koreksi  konteks pengganti dari grounding (regenerasi)
     * @return array{0:string,1:string} [system, prompt]
     */
    /**
     * Generate LANJUTAN: pecah rencana mingguan RPS committed menjadi rincian
     * per-pertemuan (untuk MK blok/profesi dengan >1 pertemuan/pekan). Satu
     * panggilan AI; topik/aktivitas/metode dari AI, durasi per pertemuan
     * dihitung deterministik oleh sistem dari estimasi waktu (bukan AI).
     *
     * @return array<int,array<int,array<string,mixed>>> peta minggu_ke => rincian tersimpan
     */
    public function generatePertemuan(RpsVersion $rps, array $opts = []): array
    {
        $mk = MataKuliah::query()
            ->where('institusi_id', $rps->institusi_id)
            ->where('kode_mk', $rps->kode_mk)
            ->first();
        if (! $mk) {
            throw new GeneratorException('Mata kuliah untuk RPS ini tidak ditemukan.');
        }

        $rows = $rps->minggu()->with('subCpmk')->orderBy('minggu_ke')->get();
        if ($rows->isEmpty()) {
            throw new GeneratorException('RPS belum memiliki rencana mingguan.');
        }

        $est  = $this->estimasi->untukMataKuliah($mk, $rows->count());
        $sesi = max(1, (int) ($est['jumlah_pertemuan'] ?? 1));
        if ($sesi <= 1) {
            throw new GeneratorException('MK ini hanya 1 pertemuan/pekan — rincian pertemuan tidak diperlukan.');
        }

        $prompt = $this->prompts->resolve('pertemuan', $rps->institusi_id, $mk->jenis_mk);
        $pola   = $mk->pola ?: 'reguler';
        $kontak = (int) ($est['tm_menit'] ?? 0) + (int) ($est['praktik_menit'] ?? 0);

        $bagian = [];
        $bagian[] = 'DATA MATA KULIAH:';
        $bagian[] = json_encode([
            'kode_mk'   => $mk->kode_mk,
            'nama'      => $mk->nama,
            'jenis_mk'  => $mk->jenis_mk,
            'sks'       => $mk->sks,
            'semester'  => $mk->semester,
            'deskripsi' => $mk->deskripsi_singkat,
        ], JSON_UNESCAPED_UNICODE);
        $bagian[] = "\nPARAMETER RINCIAN PERTEMUAN (WAJIB DIPATUHI):\n"
            . "- Susun TEPAT {$sesi} pertemuan untuk SETIAP pekan (pertemuan_ke berurutan 1..{$sesi}).\n"
            . "- Pola pelaksanaan MK: {$pola}. Jumlah pekan: {$rows->count()}.\n"
            . ($kontak > 0 ? "- Total waktu kontak per pekan ~{$kontak} menit; durasi per pertemuan dihitung sistem — JANGAN mengisi menit.\n" : '')
            . '- Pekan evaluasi/ujian: rinci kegiatan ujiannya (boleh lebih sedikit pertemuan bila memang sesi ujian).';
        $bagian[] = "\n" . $this->skopDirective();

        // Rujukan tambahan dosen dari sesi generator yang menghasilkan RPS ini
        // (BoK membatasi topik; kompetensi/bahan kajian khusus wajib tercermin).
        $sesiAsal = GenerateSession::query()->where('rps_version_id', $rps->id)->latest('id')->first();
        $tambahan = $this->konteksTambahanBlok($sesiAsal?->konteks_tambahan, 'pertemuan');
        if ($tambahan !== '') {
            $bagian[] = "\n" . $tambahan;
        }

        // Sumber materi/buku rujukan MK → topik & aktivitas per pertemuan
        // berjangkar ke pustaka nyata, bukan pengetahuan umum model.
        $pustaka = $this->pustakaContext($mk);
        if ($pustaka !== []) {
            $bagian[] = "\nPUSTAKA/REFERENSI MK (bernomor — bila topik bersumber dari pustaka, sebut [Pustaka: no]; JANGAN mengarang judul):";
            $bagian[] = json_encode($pustaka, JSON_UNESCAPED_UNICODE);
        }
        $kutipan = $this->dokumenRujukanContext((int) $rps->institusi_id, $mk);
        if ($kutipan !== []) {
            $bagian[] = "\nKUTIPAN SUMBER MATERI/BUKU RUJUKAN (PENDUKUNG substansi topik & aktivitas; JANGAN meniru format/gaya dokumen):";
            foreach ($kutipan as $k) {
                $bagian[] = '- [' . $k['sumber'] . '] ' . $k['teks'];
            }
        }

        $bagian[] = "\nRENCANA MINGGUAN RPS (BASIS PEMECAHAN — topik pertemuan wajib turunan langsung materi pekan ini, JANGAN menambah topik baru):";
        $bagian[] = json_encode($rows->map(fn($m) => array_filter([
            'minggu_ke'          => $m->minggu_ke,
            'sub_cpmk'           => $m->subCpmk?->kode,
            'sub_cpmk_deskripsi' => $m->subCpmk?->deskripsi,
            'indikator'          => $m->indikator,
            'metode_pembelajaran' => $m->metode_pembelajaran,
            'bentuk_luring'      => $m->bentuk_luring,
            'materi_pustaka'     => $m->materi_pustaka,
        ], fn($v) => $v !== null && $v !== ''))->values()->all(), JSON_UNESCAPED_UNICODE);
        $bagian[] = "\nBalas HANYA JSON valid dengan struktur berikut (tanpa teks lain):";
        $bagian[] = $prompt['schema'];

        $outcome = $this->ai->run('generate', $prompt['system'], implode("\n", $bagian), [
            'institusi_id' => $rps->institusi_id,
            'user_id'      => $opts['user_id'] ?? null,
            'entity_type'  => 'RpsVersion',
            'entity_id'    => $rps->id,
            'mode'         => 'generate:pertemuan',
            'max_tokens'   => 9000,
            'no_cache'     => true,
        ]);
        if ($outcome->failed()) {
            throw new GeneratorException('Panggilan AI gagal pada rincian pertemuan: ' . ($outcome->result->error ?? 'tidak diketahui'));
        }

        $data = $this->parseJson($outcome->text(), 'pertemuan');
        $byWeek = [];
        foreach ((array) ($data['minggu'] ?? []) as $item) {
            $ke   = (int) ($item['minggu_ke'] ?? 0);
            $list = is_array($item['pertemuan'] ?? null) ? $item['pertemuan'] : [];
            if ($ke >= 1 && $list !== []) {
                $byWeek[$ke] = $list;
            }
        }
        if ($byWeek === []) {
            throw new GeneratorException('AI tidak mengembalikan rincian pertemuan yang bisa dibaca.');
        }

        $tersimpan = [];
        foreach ($rows as $row) {
            $list = $byWeek[$row->minggu_ke] ?? null;
            if (! $list) {
                continue;
            }

            // Durasi deterministik: waktu kontak pekan ini dibagi rata jumlah pertemuan.
            $ew = is_array($row->estimasi_waktu) ? $row->estimasi_waktu : $est;
            $kontakMg = (int) ($ew['tm_menit'] ?? 0) + (int) ($ew['praktik_menit'] ?? 0);
            if ($kontakMg <= 0) {
                $kontakMg = (int) ($ew['total_menit'] ?? 0);
            }
            $durasi = $kontakMg > 0 ? (int) round($kontakMg / count($list)) : null;

            $bersih = [];
            $urut = 1;
            foreach ($list as $p) {
                if (! is_array($p)) {
                    continue;
                }
                $bersih[] = [
                    'pertemuan_ke' => $urut++,
                    'topik'        => trim((string) ($p['topik'] ?? '')) ?: null,
                    'aktivitas'    => trim((string) ($p['aktivitas'] ?? '')) ?: null,
                    'metode'       => trim((string) ($p['metode'] ?? '')) ?: null,
                    'durasi_menit' => $durasi,
                ];
            }
            if ($bersih === []) {
                continue;
            }

            $row->update(['rincian_pertemuan' => $bersih]);
            $tersimpan[$row->minggu_ke] = $bersih;
        }

        if ($tersimpan === []) {
            throw new GeneratorException('Rincian pertemuan dari AI tidak cocok dengan pekan pada RPS ini.');
        }

        return $tersimpan;
    }

    private function buildPrompt(GenerateSession $session, string $stage, array $stageCfg, MataKuliah $mk, array $koreksi = []): array
    {
        $prompt = $this->prompts->resolve($stageCfg['jenis_output'], $session->institusi_id, $mk->jenis_mk);
        $system = $prompt['system'];
        $schema = $prompt['schema'];

        $bagian = [];
        $bagian[] = 'DATA MATA KULIAH:';
        $bagian[] = json_encode(array_filter([
            'kode_mk'     => $mk->kode_mk,
            'nama'        => $mk->nama,
            'jenis_mk'    => $mk->jenis_mk,
            'sifat'       => $mk->sifat,
            'rumpun'      => $mk->rumpun,
            'sks'         => $mk->sks,
            'sks_teori'   => $mk->sks_teori,
            'sks_praktik' => $mk->sks_praktik,
            'semester'    => $mk->semester,
            'deskripsi'   => $mk->deskripsi_singkat,
        ], fn($v) => $v !== null && $v !== ''), JSON_UNESCAPED_UNICODE);

        // Jenjang program (dari level KKNI CPL) → lantai taksonomi agar CPMK/Sub-CPMK
        // tidak berada di bawah level yang layak (mis. profesi minimal C4 dominan).
        $jenjang = $this->jenjangDirective($mk);
        if ($jenjang !== '') {
            $bagian[] = "\n" . $jenjang;
        }

        // Ranah keterampilan: MK praktikum / ber-SKS praktik menuntut psikomotorik.
        $ranah = $this->ranahDirective($mk);
        if ($ranah !== '') {
            $bagian[] = "\n" . $ranah;
        }

        // Kemampuan awal: MK prasyarat = titik nol scaffolding, jangan diulang.
        $prasyarat = $this->prasyaratContext($mk);
        if ($prasyarat !== []) {
            $bagian[] = "\nMATA KULIAH PRASYARAT (kemampuan awal mahasiswa — JANGAN mengulang materinya; jadikan titik awal scaffolding):";
            $bagian[] = json_encode($prasyarat, JSON_UNESCAPED_UNICODE);
        }

        // Pagar skop: cegah AI menambah topik/kompetensi di luar MK & bahan kajian.
        $bagian[] = "\n" . $this->skopDirective();

        // Rujukan tambahan yang dimasukkan dosen saat memulai sesi (otoritatif,
        // spesifik MK ini): kompetensi khusus, Body of Knowledge, bahan kajian khusus.
        $tambahan = $this->konteksTambahanBlok($session->konteks_tambahan, (string) $stageCfg['jenis_output']);
        if ($tambahan !== '') {
            $bagian[] = "\n" . $tambahan;
        }

        // Tahap 'mingguan': durasi bervariasi per-MK (reguler/blok/profesi) →
        // suntik jumlah pekan & pola evaluasi otoritatif agar AI tidak selalu 16.
        if (($stageCfg['jenis_output'] ?? '') === 'mingguan') {
            $bagian[] = "\n" . $this->rencanaMingguanDirective($mk);
        }

        // Tahap 'penilaian': kebijakan asesmen otoritatif (bobot 100%, keselarasan
        // dengan bobot mingguan tersetujui, standar rubrik, ranah praktik/profesi).
        if (($stageCfg['jenis_output'] ?? '') === 'penilaian') {
            $bagian[] = "\n" . $this->penilaianDirective($mk, $session);
        }

        $cpls = $this->cplContext($mk);
        if ($cpls !== []) {
            $bagian[] = "\nCPL TERKAIT (CPL yang diampu MK ini — SEMUA wajib diturunkan menjadi CPMK):";
            $bagian[] = json_encode($cpls, JSON_UNESCAPED_UNICODE);
        }

        $profil = $this->profilLulusanContext($mk);
        if ($profil !== []) {
            $bagian[] = "\nPROFIL LULUSAN KURIKULUM (acuan capaian):";
            $bagian[] = json_encode($profil, JSON_UNESCAPED_UNICODE);
        }

        $bk = $this->bahanKajianContext($mk);
        if ($bk !== []) {
            $arahBk = match ((string) ($stageCfg['jenis_output'] ?? '')) {
                'cpmk'     => 'SETIAP bahan kajian di bawah WAJIB tercermin pada minimal satu CPMK — jangan ada bahan kajian yang tidak terpetakan',
                'sub_cpmk' => 'jabarkan bahan kajian di bawah menjadi Sub-CPMK; setiap bahan kajian terwakili minimal satu Sub-CPMK',
                'mingguan' => 'WAJIB dijadikan basis materi_pustaka tiap minggu, dipilih sesuai Sub-CPMK',
                default    => 'jadikan acuan substansi capaian',
            };
            $bagian[] = "\nBAHAN KAJIAN MK ({$arahBk}; "
                . "field \"cpl\" tiap bahan kajian menandai CPL yang ditopangnya — pilih/selaraskan dengan CPL induk):";
            $bagian[] = json_encode($bk, JSON_UNESCAPED_UNICODE);
        }

        $pustaka = $this->pustakaContext($mk);
        if ($pustaka !== []) {
            $bagian[] = "\nPUSTAKA/REFERENSI MK (bernomor — rujuk PERSIS memakai nomor 'no' pada [Pustaka: ...]; HANYA gunakan referensi dari daftar ini, jangan mengarang judul):";
            $bagian[] = json_encode($pustaka, JSON_UNESCAPED_UNICODE);
        }

        // Kutipan dokumen rujukan KEILMUAN (opt-in via toggle sumber_konten).
        // Pendukung substansi — instrumen utama di atas tetap otoritatif.
        $kutipan = $this->dokumenRujukanContext((int) $session->institusi_id, $mk);
        if ($kutipan !== []) {
            $bagian[] = "\nKUTIPAN SUMBER MATERI/BUKU RUJUKAN (PENDUKUNG — pakai untuk memperkaya/meluruskan substansi; instrumen utama di atas tetap otoritatif; JANGAN meniru format/gaya dokumen):";
            foreach ($kutipan as $k) {
                $bagian[] = '- [' . $k['sumber'] . '] ' . $k['teks'];
            }
        }

        foreach ($stageCfg['context_from'] as $dep) {
            $draf = $session->draf[$dep] ?? null;
            if ($draf !== null) {
                $bagian[] = "\nHASIL TAHAP '" . strtoupper($dep) . "' (sudah disetujui):";
                $bagian[] = json_encode($draf, JSON_UNESCAPED_UNICODE);
            }
        }

        if ($koreksi !== []) {
            $bagian[] = "\nKOREKSI WAJIB (keluaran sebelumnya tak sesuai bukti; perbaiki agar selaras konteks sahih berikut):";
            foreach ($koreksi as $k) {
                $bagian[] = '- ' . $k;
            }
        }

        $bagian[] = "\nBalas HANYA JSON valid dengan struktur berikut (tanpa teks lain):";
        $bagian[] = $schema;

        return [$system, implode("\n", $bagian)];
    }

    /**
     * Direktif jenjang program: level KKNI tertinggi dari CPL kurikulum → lantai
     * level taksonomi CPMK/Sub-CPMK. Kosong bila CPL tak mencantumkan level_kkni.
     */
    private function jenjangDirective(MataKuliah $mk): string
    {
        if (! $mk->kurikulum_id) {
            return '';
        }

        $level = Cpl::query()
            ->where('kurikulum_id', $mk->kurikulum_id)
            ->whereNotNull('level_kkni')
            ->pluck('level_kkni')
            ->map(fn($v) => (int) filter_var((string) $v, FILTER_SANITIZE_NUMBER_INT))
            ->filter(fn($v) => $v > 0)
            ->max();

        if (! $level) {
            return '';
        }

        $aturan = match (true) {
            $level >= 8 => 'CPMK dominan C5-C6 (mengevaluasi/mencipta); JANGAN ada CPMK di bawah C4. Sub-CPMK paling rendah C3.',
            $level >= 7 => 'jenjang PROFESI — CPMK dominan C4-C6 disertai keterampilan P3+ (penerapan nyata di lapangan/klinik/wahana); JANGAN ada CPMK di bawah C3; Sub-CPMK C1-C2 hanya boleh sebagai pengantar paling awal.',
            $level >= 6 => 'jenjang SARJANA — CPMK dominan C3-C5; JANGAN ada CPMK level C1-C2 (level rendah hanya boleh pada Sub-CPMK pengantar).',
            default     => "sesuaikan kedalaman dengan level KKNI {$level}.",
        };

        return "JENJANG PROGRAM (WAJIB DIPATUHI):\n"
            . "- Level KKNI tertinggi pada CPL kurikulum ini: {$level}.\n"
            . "- Aturan level taksonomi: {$aturan}";
    }

    /**
     * Pagar skop: keluaran wajib berada dalam lingkup MK & bahan kajian yang
     * diberikan; cegah model menambah topik dari pengetahuan umum di luar konteks.
     */
    private function skopDirective(): string
    {
        return "BATASAN SKOP (WAJIB DIPATUHI):\n"
            . "- Seluruh capaian, materi, contoh, dan aktivitas HARUS berada dalam lingkup mata kuliah ini (nama & deskripsi) serta BAHAN KAJIAN MK pada konteks.\n"
            . "- JANGAN menambah topik/kompetensi di luar bahan kajian; bila merinci, rincian tetap turunan langsung bahan kajian tsb.\n"
            . "- JANGAN menciptakan entitas baru (topik, bahan kajian, referensi) dari pengetahuan umum di luar konteks yang diberikan.";
    }

    /**
     * Direktif ranah keterampilan: MK praktikum wajib dominan psikomotorik;
     * MK campuran (ber-SKS praktik) wajib menyertakan capaian psikomotorik
     * proporsional. Kosong untuk MK teori murni.
     */
    private function ranahDirective(MataKuliah $mk): string
    {
        $praktik = (int) ($mk->sks_praktik ?? 0);
        if ($mk->jenis_mk === 'praktikum') {
            return "RANAH KETERAMPILAN (WAJIB DIPATUHI):\n"
                . "- MK ini PRAKTIKUM: CPMK/Sub-CPMK WAJIB dominan ranah PSIKOMOTORIK (P2-P4) dengan KKO keterampilan (mendemonstrasikan, mengoperasikan, melakukan, mengukur, merakit, mengkalibrasi); kognitif hanya pendukung.\n"
                . '- Indikator & asesmen berbasis UNJUK KERJA yang teramati (observasi/laporan/demonstrasi), bukan hafalan.';
        }
        if ($praktik > 0) {
            return "RANAH KETERAMPILAN (WAJIB DIPATUHI):\n"
                . "- MK ini memuat {$praktik} SKS PRAKTIK di samping teorinya: sertakan CPMK/Sub-CPMK ranah psikomotorik (P2+) untuk komponen praktiknya, proporsional dengan bobot SKS praktik, di samping capaian kognitifnya.";
        }

        return '';
    }

    /**
     * Direktif kebijakan asesmen untuk tahap 'penilaian': total bobot, keselarasan
     * dengan bobot mingguan yang sudah disetujui, standar rubrik, dan ranah
     * khusus MK praktik/profesi.
     */
    private function penilaianDirective(MataKuliah $mk, GenerateSession $session): string
    {
        $baris = ['PARAMETER PENILAIAN (WAJIB DIPATUHI):'];
        $baris[] = '- Total bobot_persen SEMUA komponen TEPAT 100.';

        // Distribusi bobot mingguan tersetujui → jangkar konsistensi bobot komponen.
        $mingguan = ($session->draf ?? [])['mingguan']['minggu'] ?? [];
        $distribusi = [];
        foreach ((array) $mingguan as $m) {
            $ke = (int) ($m['minggu_ke'] ?? 0);
            $bobot = (float) ($m['bobot_penilaian'] ?? 0);
            if ($ke >= 1 && $bobot > 0) {
                $distribusi['minggu_' . $ke] = ($distribusi['minggu_' . $ke] ?? 0) + $bobot;
            }
        }
        if ($distribusi !== []) {
            $baris[] = '- SELARASKAN dengan bobot_penilaian rencana mingguan yang sudah disetujui: komponen yang mengukur Sub-CPMK pekan tertentu harus konsisten dengan bobot pekan tsb. Distribusi bobot mingguan: '
                . json_encode($distribusi, JSON_UNESCAPED_UNICODE) . '.';
        }

        $baris[] = '- Rubrik analitik: default 4 level skala (Kurang/Cukup/Baik/Sangat Baik) dengan jumlah bobot kriteria = 100, kecuali konteks menyatakan standar lain.';

        if ($mk->jenis_mk === 'praktikum' || (int) ($mk->sks_praktik ?? 0) > 0) {
            $baris[] = '- MK ber-praktik: mayoritas bobot pada asesmen UNJUK KERJA (laporan/observasi/demonstrasi/proyek) ber-rubrik, bukan tes tulis.';
        }
        if (($mk->pola ?: 'reguler') === 'profesi') {
            $baris[] = '- MK PROFESI: JANGAN membuat UTS/UAS tulis — gunakan log book, ujian kasus/OSCE, dan penilaian pembimbing/preseptor.';
        }

        return implode("\n", $baris);
    }

    /**
     * Daftar MK prasyarat (kode dipisah koma/titik-koma) sebagai kemampuan awal.
     * Kode yang tak ditemukan di master tetap disebut agar AI tahu keberadaannya.
     *
     * @return array<int,array<string,string>>
     */
    private function prasyaratContext(MataKuliah $mk): array
    {
        $raw = trim((string) ($mk->prasyarat_kode ?? ''));
        if ($raw === '') {
            return [];
        }

        $kodes = collect(preg_split('/[,;\\/]+/', $raw) ?: [])
            ->map(fn($k) => trim((string) $k))
            ->filter()
            ->unique()
            ->values();
        if ($kodes->isEmpty()) {
            return [];
        }

        $rows = MataKuliah::query()
            ->where('institusi_id', $mk->institusi_id)
            ->whereIn('kode_mk', $kodes)
            ->get(['kode_mk', 'nama', 'deskripsi_singkat'])
            ->keyBy('kode_mk');

        return $kodes->map(function ($kode) use ($rows) {
            $row = $rows->get($kode);

            return array_filter([
                'kode_mk'   => $kode,
                'nama'      => $row?->nama,
                'deskripsi' => $row?->deskripsi_singkat,
            ], fn($v) => $v !== null && $v !== '');
        })->all();
    }

    /**
     * Direktif otoritatif jumlah pekan + pola evaluasi untuk tahap 'mingguan',
     * disesuaikan pola pelaksanaan MK (reguler/blok/profesi). Jumlah pekan & estimasi
     * beban di-resolve oleh EstimasiWaktuService (sumber tunggal aturan SKS/waktu).
     */
    private function rencanaMingguanDirective(MataKuliah $mk): string
    {
        $n    = $this->estimasi->jumlahMingguUntuk($mk);
        $pola = $mk->pola ?: 'reguler';
        $est  = $this->estimasi->untukMataKuliah($mk, $n);
        $jam  = round(((int) ($est['total_menit'] ?? 0)) / 60, 1);
        $sesi = (int) ($est['jumlah_pertemuan'] ?? 0);

        $evaluasi = match ($pola) {
            'blok'    => "Ini mata kuliah BLOK berdurasi {$n} pekan. Letakkan evaluasi/ujian AKHIR BLOK pada pekan terakhir; JANGAN memaksakan UTS di tengah semester.",
            'profesi' => "Ini mata kuliah PRAKTEK PROFESI/klinik berdurasi {$n} pekan. Penilaian berbasis KINERJA (log book, ujian kasus/OSCE, penilaian pembimbing/preseptor) — BUKAN UTS/UAS tulis. Tiap pekan berisi aktivitas/rotasi/stase klinik yang relevan.",
            default   => (function () use ($mk, $n) {
                $p = $this->estimasi->pekanEvaluasi($mk->institusi_id, $n);
                return "Letakkan UTS pada pekan ke-{$p['uts']} dan UAS pada pekan ke-{$p['uas']} SESUAI konfigurasi aturan program studi; JANGAN menaruhnya di pekan lain.";
            })(),
        };

        $beban = $sesi > 0
            ? "- Perkiraan beban per pekan: ~{$jam} jam ({$est['total_menit']} menit), sekitar {$sesi} pertemuan/pekan.\n"
            : '';

        return "PARAMETER RENCANA MINGGUAN (WAJIB DIPATUHI):\n"
            . "- Gunakan TEPAT {$n} pekan (minggu_ke hanya boleh bernilai 1..{$n}); JANGAN membuat pekan di luar rentang itu.\n"
            . "- SEMUA Sub-CPMK WAJIB tercakup, masing-masing minimal SATU baris. Jumlah BARIS boleh LEBIH dari jumlah pekan: "
            . "bila jumlah pekan lebih sedikit dari jumlah Sub-CPMK (mis. blok {$n} pekan dengan banyak pertemuan), "
            . "buat BEBERAPA BARIS dengan minggu_ke yang SAMA — satu baris per Sub-CPMK, berurutan sesuai skenario pembelajaran.\n"
            . "- Pola pelaksanaan: {$pola}.\n"
            . $beban
            . "- {$evaluasi}";
    }

    private function cplContext(MataKuliah $mk): array
    {
        if (! $mk->kurikulum_id) {
            return [];
        }

        $query = Cpl::query()->where('kurikulum_id', $mk->kurikulum_id);

        // Bila MK sudah dipetakan ke CPL tertentu (matriks CPL×MK / mk_cpl),
        // batasi ke subset itu — satu CPL disebar ke banyak MK, jadi hanya CPL
        // yang diampu MK ini yang wajib diturunkan jadi CPMK. Fallback: seluruh
        // CPL kurikulum bila pemetaan belum diisi.
        $cplIds = MkCpl::query()
            ->where('institusi_id', $mk->institusi_id)
            ->where('kode_mk', $mk->kode_mk)
            ->pluck('cpl_id');
        if ($cplIds->isNotEmpty()) {
            $query->whereIn('id', $cplIds);
        }

        return $query->get(['kode', 'deskripsi', 'aspek', 'level_kkni'])
            ->map(fn($c) => array_filter([
                'kode'       => $c->kode,
                'deskripsi'  => $c->deskripsi,
                'aspek'      => $c->aspek,
                'level_kkni' => $c->level_kkni,
            ], fn($v) => $v !== null && $v !== ''))
            ->all();
    }

    /**
     * Tegakkan prasyarat matriks CPL×MK sebelum menurunkan CPMK: MK dalam
     * kurikulum ber-CPL WAJIB dipetakan ke minimal satu CPL (mk_cpl). Dilewati
     * bila MK di luar kurikulum atau kurikulum belum punya CPL sama sekali.
     */
    private function pastikanMatriksCplSiap(MataKuliah $mk): void
    {
        if (! $mk->kurikulum_id) {
            return;
        }
        if (! Cpl::where('kurikulum_id', $mk->kurikulum_id)->exists()) {
            return;
        }

        $terpetakan = MkCpl::query()
            ->where('institusi_id', $mk->institusi_id)
            ->where('kode_mk', $mk->kode_mk)
            ->exists();

        if (! $terpetakan) {
            throw new GeneratorException(
                'Mata kuliah ini belum dipetakan ke CPL mana pun pada matriks CPL×Mata Kuliah. '
                    . 'Petakan minimal satu CPL untuk MK ini sebelum menyusun RPS agar keterunutan CPL→CPMK terjaga.'
            );
        }
    }

    private function profilLulusanContext(MataKuliah $mk): array
    {
        if (! $mk->kurikulum_id) {
            return [];
        }

        return ProfilLulusan::query()
            ->where('kurikulum_id', $mk->kurikulum_id)
            ->get(['kode', 'deskripsi'])
            ->map(fn($p) => ['kode' => $p->kode, 'deskripsi' => $p->deskripsi])
            ->all();
    }

    private function bahanKajianContext(MataKuliah $mk): array
    {
        // Peta CPL yang ditopang tiap bahan kajian (matriks CPL x BK) agar AI
        // dapat menyelaraskan materi_pustaka tiap pekan ke CPL induk Sub-CPMK.
        $cplPerBk = $this->cplPerBahanKajian($mk);

        // Prioritaskan BK yang sudah dipetakan ke MK (mk_bahan_kajian);
        // fallback ke BK kurikulum bila belum ada mapping.
        $mapped = MkBahanKajian::query()
            ->where('institusi_id', $mk->institusi_id)
            ->where('kode_mk', $mk->kode_mk)
            ->with(['bahanKajian.keterampilan'])
            ->get()
            ->map(function ($mkbk) use ($cplPerBk) {
                $bk = $mkbk->bahanKajian;
                if (! $bk) {
                    return null;
                }
                return array_filter([
                    'nama'         => (string) ($bk->nama ?? ''),
                    'deskripsi'    => $bk->deskripsi,
                    'cpl'          => $cplPerBk[$bk->id] ?? [],
                    'keterampilan' => $bk->keterampilan
                        ->map(fn($k) => (string) ($k->deskripsi ?? ''))
                        ->filter()->values()->all(),
                ], fn($v) => $v !== null && $v !== [] && $v !== '');
            })
            ->filter()->values()->all();

        if (! empty($mapped)) {
            return $mapped;
        }
        if (! $mk->kurikulum_id) {
            return [];
        }
        return BahanKajian::query()
            ->where('kurikulum_id', $mk->kurikulum_id)
            ->get(['id', 'nama', 'deskripsi'])
            ->map(fn($b) => array_filter([
                'nama'      => $b->nama,
                'deskripsi' => $b->deskripsi,
                'cpl'       => $cplPerBk[$b->id] ?? [],
            ], fn($v) => $v !== null && $v !== [] && $v !== ''))
            ->all();
    }

    /**
     * Peta bahan_kajian_id => daftar kode CPL yang ditopangnya, dari matriks
     * CPL x Bahan Kajian (cpl_bahan_kajian) dalam lingkup kurikulum MK.
     *
     * @return array<int,list<string>>
     */
    private function cplPerBahanKajian(MataKuliah $mk): array
    {
        if (! $mk->kurikulum_id) {
            return [];
        }

        $peta = [];
        CplBahanKajian::query()
            ->whereHas('cpl', fn($q) => $q->where('kurikulum_id', $mk->kurikulum_id))
            ->with('cpl:id,kode')
            ->get()
            ->each(function ($row) use (&$peta) {
                $kode = $row->cpl?->kode;
                if ($kode) {
                    $peta[$row->bahan_kajian_id][] = (string) $kode;
                }
            });

        return $peta;
    }

    private function pustakaContext(MataKuliah $mk): array
    {
        // Bernomor eksplisit agar sitasi [Pustaka: n] keluaran AI presisi
        // (tidak menebak urutan sendiri).
        $refs = Referensi::query()
            ->where('institusi_id', $mk->institusi_id)
            ->where('kode_mk', $mk->kode_mk)
            ->orderBy('id')
            ->get(['tipe', 'sitasi']);
        return $refs->values()->map(fn($r, $i) => [
            'no'     => $i + 1,
            'tipe'   => $r->tipe ?: 'utama',
            'sitasi' => $r->sitasi,
        ])->all();
    }

    /**
     * Blok prompt dari rujukan tambahan yang dimasukkan dosen saat memulai sesi:
     * kompetensi khusus MK, Body of Knowledge, dan bahan kajian khusus MK.
     * Arahan pemakaian tiap field disesuaikan dengan tahap yang sedang digenerate.
     * Kosong bila dosen tidak mengisi apa pun.
     */
    private function konteksTambahanBlok(mixed $konteks, string $stage): string
    {
        if (! is_array($konteks)) {
            return '';
        }

        $arahan = [
            'cpmk' => [
                'kompetensi_khusus'   => 'WAJIB diturunkan menjadi CPMK — setiap kompetensi di sini terwakili minimal satu CPMK',
                'bok'                 => 'batas cakupan keilmuan — rumusan CPMK TIDAK BOLEH keluar dari cakupan ini',
                'bahan_kajian_khusus' => 'substansi wajib saat merumuskan CPMK; gabungkan dengan BAHAN KAJIAN MK kurikulum',
            ],
            'sub_cpmk' => [
                'kompetensi_khusus'   => 'jabarkan menjadi Sub-CPMK yang terukur dengan indikator operasional',
                'bok'                 => 'batas cakupan keilmuan — Sub-CPMK TIDAK BOLEH keluar dari cakupan ini',
                'bahan_kajian_khusus' => 'jadikan basis penjabaran Sub-CPMK bersama bahan kajian kurikulum',
            ],
            'mingguan' => [
                'kompetensi_khusus'   => 'aktivitas & indikator mingguan wajib melatih dan mengukur kompetensi ini',
                'bok'                 => 'peta & batas materi — topik mingguan TIDAK BOLEH keluar dari cakupan ini',
                'bahan_kajian_khusus' => 'WAJIB terdistribusi pada topik mingguan; gabungkan dengan BAHAN KAJIAN MK kurikulum',
            ],
            'penilaian' => [
                'kompetensi_khusus'   => 'WAJIB ada komponen/teknik penilaian yang secara eksplisit mengukur kompetensi ini',
                'bok'                 => 'substansi instrumen penilaian (soal/tugas/rubrik) TIDAK BOLEH keluar dari cakupan ini',
                'bahan_kajian_khusus' => 'pastikan terwakili pada materi yang dinilai',
            ],
            'pertemuan' => [
                'kompetensi_khusus'   => 'aktivitas pertemuan wajib melatih kompetensi ini',
                'bok'                 => 'topik pertemuan TIDAK BOLEH keluar dari cakupan ini',
                'bahan_kajian_khusus' => 'pastikan tercermin pada topik pertemuan pekan terkait',
            ],
        ];

        $peta = [
            'kompetensi_khusus'   => 'KOMPETENSI KHUSUS MATA KULIAH',
            'bok'                 => 'BODY OF KNOWLEDGE / CAKUPAN KEILMUAN MK',
            'bahan_kajian_khusus' => 'BAHAN KAJIAN KHUSUS MK DARI DOSEN',
        ];
        $arah = $arahan[$stage] ?? $arahan['mingguan'];

        $baris = [];
        foreach ($peta as $kunci => $label) {
            $isi = trim((string) ($konteks[$kunci] ?? ''));
            if ($isi !== '') {
                $baris[] = $label . ' (' . ($arah[$kunci] ?? 'patuhi') . '):';
                $baris[] = $isi;
                $baris[] = '';
            }
        }

        if ($baris === []) {
            return '';
        }

        array_unshift($baris, 'RUJUKAN TAMBAHAN DARI DOSEN (OTORITATIF untuk MK ini — patuhi; bila bertentangan dengan pengetahuan umum, blok ini yang menang):');

        return rtrim(implode("\n", $baris));
    }

    /**
     * Kutipan RAG dari dokumen rujukan ber-flag sumber_konten (keilmuan) yang
     * relevan dengan MK. Kosong bila fitur nonaktif, tak ada dokumen ber-flag
     * terindeks, atau retrieval gagal (generate tak boleh ikut gagal).
     *
     * @return array<int,array{sumber:string,teks:string}>
     */
    private function dokumenRujukanContext(int $institusiId, MataKuliah $mk): array
    {
        if (! config('generator.rag.enabled', true)) {
            return [];
        }

        // Dokumen yang DITAUTKAN ke MK ini = sumber materi utama → retrieval
        // dibatasi ke sana. Tanpa tautan, fallback ke semua dokumen keilmuan
        // (sumber_konten) institusi seperti sebelumnya.
        $tertaut = MkDokumenRujukan::query()
            ->where('institusi_id', $mk->institusi_id)
            ->where('kode_mk', $mk->kode_mk)
            ->pluck('dokumen_rujukan_id')
            ->all();

        $ada = DokumenChunk::whereNotNull('embedding')
            ->when(
                $tertaut !== [],
                fn($q) => $q->whereIn('dokumen_id', $tertaut),
                fn($q) => $q->whereHas('dokumen', fn($qq) => $qq->where('institusi_id', $institusiId)->where('sumber_konten', true)),
            )
            ->exists();
        if (! $ada) {
            return [];
        }

        $query = trim(implode(' — ', array_filter([
            (string) $mk->nama,
            (string) ($mk->deskripsi_singkat ?? ''),
            implode(', ', array_slice(array_column($this->bahanKajianContext($mk), 'nama'), 0, 8)),
        ])));
        if ($query === '') {
            return [];
        }

        try {
            $opsi = ['min_score' => (float) config('generator.rag.min_score', 0.5)];
            if ($tertaut !== []) {
                $opsi['dokumen_ids'] = $tertaut; // buku pilihan dosen utk MK ini
            } else {
                $opsi['sumber_konten'] = true;
            }
            $hits = $this->embeddings->search(
                $institusiId,
                mb_substr($query, 0, 1500),
                max(1, (int) config('generator.rag.top_k', 4)),
                $opsi,
            );
        } catch (\Throwable) {
            return []; // retrieval gagal → lanjut tanpa kutipan
        }

        $out = [];
        foreach ($hits as $h) {
            $chunk = $h['chunk'];
            $out[] = [
                'sumber' => (string) ($chunk->dokumen?->judul ?? 'Dokumen rujukan'),
                'teks'   => mb_strimwidth(trim((string) $chunk->teks), 0, 600, '…'),
            ];
        }

        return $out;
    }

    private function parseJson(string $text, string $stage): array
    {
        $clean = trim($text);
        // Buang pagar markdown ```json ... ```
        $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $clean);
        $clean = trim((string) $clean);

        $data = json_decode($clean, true);

        // Fallback: ekstrak objek JSON pertama bila ada teks pembungkus.
        if (! is_array($data)) {
            $start = strpos($clean, '{');
            $end = strrpos($clean, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $data = json_decode(substr($clean, $start, $end - $start + 1), true);
            }
        }

        if (! is_array($data)) {
            throw new GeneratorException(
                "Keluaran AI tahap '{$stage}' bukan JSON valid: " . mb_substr($text, 0, 120)
            );
        }

        return $data;
    }
}
