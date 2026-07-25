<?php

namespace App\Services\Generator;

use App\Models\BahanKajian;
use App\Models\Cpl;
use App\Models\Cpmk;
use App\Models\DokumenChunk;
use App\Models\GenerateSession;
use App\Models\Indikator;
use App\Models\KomponenPenilaian;
use App\Models\MataKuliah;
use App\Models\MkBahanKajian;
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
use App\Services\Rps\EstimasiWaktuService;
use Illuminate\Support\Facades\DB;

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
        $draf[$stage] = $data;
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
            $draf[$stage] = $edited;
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
        $bagian[] = json_encode([
            'kode_mk'   => $mk->kode_mk,
            'nama'      => $mk->nama,
            'jenis_mk'  => $mk->jenis_mk,
            'sks'       => $mk->sks,
            'semester'  => $mk->semester,
            'deskripsi' => $mk->deskripsi_singkat,
        ], JSON_UNESCAPED_UNICODE);

        // Jenjang program (dari level KKNI CPL) → lantai taksonomi agar CPMK/Sub-CPMK
        // tidak berada di bawah level yang layak (mis. profesi minimal C4 dominan).
        $jenjang = $this->jenjangDirective($mk);
        if ($jenjang !== '') {
            $bagian[] = "\n" . $jenjang;
        }

        // Pagar skop: cegah AI menambah topik/kompetensi di luar MK & bahan kajian.
        $bagian[] = "\n" . $this->skopDirective();

        // Tahap 'mingguan': durasi bervariasi per-MK (reguler/blok/profesi) →
        // suntik jumlah pekan & pola evaluasi otoritatif agar AI tidak selalu 16.
        if (($stageCfg['jenis_output'] ?? '') === 'mingguan') {
            $bagian[] = "\n" . $this->rencanaMingguanDirective($mk);
        }

        $cpls = $this->cplContext($mk);
        if ($cpls !== []) {
            $bagian[] = "\nCPL TERKAIT:";
            $bagian[] = json_encode($cpls, JSON_UNESCAPED_UNICODE);
        }

        $profil = $this->profilLulusanContext($mk);
        if ($profil !== []) {
            $bagian[] = "\nPROFIL LULUSAN KURIKULUM (acuan capaian):";
            $bagian[] = json_encode($profil, JSON_UNESCAPED_UNICODE);
        }

        $bk = $this->bahanKajianContext($mk);
        if ($bk !== []) {
            $bagian[] = "\nBAHAN KAJIAN MK (WAJIB dijadikan basis materi_pustaka tiap minggu, dipilih sesuai Sub-CPMK):";
            $bagian[] = json_encode($bk, JSON_UNESCAPED_UNICODE);
        }

        $pustaka = $this->pustakaContext($mk);
        if ($pustaka !== []) {
            $bagian[] = "\nPUSTAKA/REFERENSI MK (HANYA gunakan referensi dari daftar ini, jangan mengarang judul):";
            $bagian[] = json_encode($pustaka, JSON_UNESCAPED_UNICODE);
        }

        // Kutipan dokumen rujukan KEILMUAN (opt-in via toggle sumber_konten).
        // Pendukung substansi — instrumen utama di atas tetap otoritatif.
        $kutipan = $this->dokumenRujukanContext($session, $mk);
        if ($kutipan !== []) {
            $bagian[] = "\nKUTIPAN DOKUMEN RUJUKAN KEILMUAN (PENDUKUNG — pakai untuk memperkaya/meluruskan substansi; instrumen utama di atas tetap otoritatif; JANGAN meniru format/gaya dokumen):";
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
            default   => "Sertakan UTS pada sekitar pekan tengah dan UAS pada pekan terakhir.",
        };

        $beban = $sesi > 0
            ? "- Perkiraan beban per pekan: ~{$jam} jam ({$est['total_menit']} menit), sekitar {$sesi} pertemuan/pekan.\n"
            : '';

        return "PARAMETER RENCANA MINGGUAN (WAJIB DIPATUHI):\n"
            . "- Susun TEPAT {$n} pekan (minggu_ke berurutan 1..{$n}); JANGAN kurang atau lebih dari {$n}.\n"
            . "- Pola pelaksanaan: {$pola}.\n"
            . $beban
            . "- {$evaluasi}";
    }

    private function cplContext(MataKuliah $mk): array
    {
        if (! $mk->kurikulum_id) {
            return [];
        }

        return Cpl::query()
            ->where('kurikulum_id', $mk->kurikulum_id)
            ->get(['kode', 'deskripsi', 'aspek', 'level_kkni'])
            ->map(fn($c) => array_filter([
                'kode'       => $c->kode,
                'deskripsi'  => $c->deskripsi,
                'aspek'      => $c->aspek,
                'level_kkni' => $c->level_kkni,
            ], fn($v) => $v !== null && $v !== ''))
            ->all();
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
        // Prioritaskan BK yang sudah dipetakan ke MK (mk_bahan_kajian);
        // fallback ke BK kurikulum bila belum ada mapping.
        $mapped = MkBahanKajian::query()
            ->where('institusi_id', $mk->institusi_id)
            ->where('kode_mk', $mk->kode_mk)
            ->with(['bahanKajian.keterampilan'])
            ->get()
            ->map(function ($mkbk) {
                $bk = $mkbk->bahanKajian;
                if (! $bk) {
                    return null;
                }
                return [
                    'nama'         => (string) ($bk->nama ?? ''),
                    'deskripsi'    => $bk->deskripsi,
                    'keterampilan' => $bk->keterampilan
                        ->map(fn($k) => (string) ($k->deskripsi ?? ''))
                        ->filter()->values()->all(),
                ];
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
            ->get(['nama', 'deskripsi'])
            ->map(fn($b) => ['nama' => $b->nama, 'deskripsi' => $b->deskripsi])
            ->all();
    }

    private function pustakaContext(MataKuliah $mk): array
    {
        $refs = Referensi::query()
            ->where('institusi_id', $mk->institusi_id)
            ->where('kode_mk', $mk->kode_mk)
            ->get(['tipe', 'sitasi']);
        return $refs->map(fn($r) => [
            'tipe'   => $r->tipe ?: 'utama',
            'sitasi' => $r->sitasi,
        ])->values()->all();
    }

    /**
     * Kutipan RAG dari dokumen rujukan ber-flag sumber_konten (keilmuan) yang
     * relevan dengan MK. Kosong bila fitur nonaktif, tak ada dokumen ber-flag
     * terindeks, atau retrieval gagal (generate tak boleh ikut gagal).
     *
     * @return array<int,array{sumber:string,teks:string}>
     */
    private function dokumenRujukanContext(GenerateSession $session, MataKuliah $mk): array
    {
        if (! config('generator.rag.enabled', true)) {
            return [];
        }

        $ada = DokumenChunk::whereNotNull('embedding')
            ->whereHas('dokumen', fn($q) => $q->where('institusi_id', $session->institusi_id)->where('sumber_konten', true))
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
            $hits = $this->embeddings->search(
                (int) $session->institusi_id,
                mb_substr($query, 0, 1500),
                max(1, (int) config('generator.rag.top_k', 4)),
                ['min_score' => (float) config('generator.rag.min_score', 0.5), 'sumber_konten' => true],
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
