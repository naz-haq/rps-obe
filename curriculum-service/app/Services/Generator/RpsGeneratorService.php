<?php

namespace App\Services\Generator;

use App\Models\BahanKajian;
use App\Models\Cpl;
use App\Models\Cpmk;
use App\Models\DokumenChunk;
use App\Models\GenerateSession;
use App\Models\Indikator;
use App\Models\KomponenPenilaian;
use App\Models\KonfigurasiAturan;
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
        $outcome = null;
        $data = [];
        $validasi = null;

        for ($percobaan = 0; $percobaan <= $maks; $percobaan++) {
            $outcome = $this->runGenerate($session, $stage, $stageCfg, $mk, $koreksi);
            $data = $this->parseJson($outcome->text(), $stage);

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
    private function runGenerate(GenerateSession $session, string $stage, array $stageCfg, MataKuliah $mk, array $koreksi): AiOutcome
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
            ->whereHas('dokumen', fn($q) => $q->where('institusi_id', $session->institusi_id))
            ->exists();
        if (! $adaBukti) {
            return ['bersih' => true, 'konteks' => [], 'lolos' => true, 'ditolak' => [], 'hasil' => [], 'jumlah_klaim' => 0, 'dilewati' => 'tak ada dokumen rujukan'];
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

        return Cpl::where('kurikulum_id', $mk->kurikulum_id)->where('kode', $kode)->first();
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
     * Jumlah pekan efektif MK: nilai eksplisit per-MK menimpa; jika kosong pakai
     * default global `konfigurasi_aturan.jumlah_minggu.minggu_efektif` (fallback 16).
     * Khusus pola 'profesi' tanpa nilai eksplisit: dihitung dari SKS via aturan
     * `konfigurasi_aturan.konversi_minggu_profesi.minggu_per_sks` (default 1).
     * Fakta kaku → sumber utamanya isian manusia (Kaprodi), bukan tebakan AI.
     */
    private function jumlahMingguUntuk(MataKuliah $mk): int
    {
        if (! empty($mk->jumlah_minggu) && (int) $mk->jumlah_minggu > 0) {
            return (int) $mk->jumlah_minggu;
        }

        // Profesi/klinik: durasi diturunkan dari beban SKS (bila belum diisi manual).
        if (($mk->pola ?: 'reguler') === 'profesi') {
            $dari = $this->mingguProfesiDariSks($mk);
            if ($dari > 0) {
                return $dari;
            }
        }

        $nilai = KonfigurasiAturan::query()
            ->where('jenis_aturan', 'jumlah_minggu')
            ->where(fn($q) => $q->where('institusi_id', $mk->institusi_id)->orWhereNull('institusi_id'))
            ->orderByRaw('institusi_id IS NULL')
            ->value('nilai');

        $efektif = is_array($nilai) ? ($nilai['minggu_efektif'] ?? null) : null;

        return is_numeric($efektif) && (int) $efektif > 0 ? (int) $efektif : 16;
    }

    /**
     * Pekan MK profesi diturunkan dari total SKS × faktor (minggu_per_sks).
     * Faktor dari `konfigurasi_aturan.konversi_minggu_profesi` (default 1).
     */
    private function mingguProfesiDariSks(MataKuliah $mk): int
    {
        $nilai = KonfigurasiAturan::query()
            ->where('jenis_aturan', 'konversi_minggu_profesi')
            ->where(fn($q) => $q->where('institusi_id', $mk->institusi_id)->orWhereNull('institusi_id'))
            ->orderByRaw('institusi_id IS NULL')
            ->value('nilai');

        $faktor = is_array($nilai) ? ($nilai['minggu_per_sks'] ?? null) : null;
        $faktor = is_numeric($faktor) && (float) $faktor > 0 ? (float) $faktor : 1.0;

        return (int) ceil(((int) $mk->sks_teori + (int) $mk->sks_praktik) * $faktor);
    }

    /**
     * Direktif otoritatif jumlah pekan + pola evaluasi untuk tahap 'mingguan',
     * disesuaikan pola pelaksanaan MK (reguler/blok/profesi).
     */
    private function rencanaMingguanDirective(MataKuliah $mk): string
    {
        $n = $this->jumlahMingguUntuk($mk);
        $pola = $mk->pola ?: 'reguler';

        $evaluasi = match ($pola) {
            'blok'    => "Ini mata kuliah BLOK berdurasi {$n} pekan. Letakkan evaluasi/ujian AKHIR BLOK pada pekan terakhir; JANGAN memaksakan UTS di tengah semester.",
            'profesi' => "Ini mata kuliah PRAKTEK PROFESI/klinik berdurasi {$n} pekan. Penilaian berbasis KINERJA (log book, ujian kasus/OSCE, penilaian pembimbing/preseptor) — BUKAN UTS/UAS tulis. Tiap pekan berisi aktivitas/rotasi/stase klinik yang relevan.",
            default   => "Sertakan UTS pada sekitar pekan tengah dan UAS pada pekan terakhir.",
        };

        return "PARAMETER RENCANA MINGGUAN (WAJIB DIPATUHI):\n"
            . "- Susun TEPAT {$n} pekan (minggu_ke berurutan 1..{$n}); JANGAN kurang atau lebih dari {$n}.\n"
            . "- Pola pelaksanaan: {$pola}.\n"
            . "- {$evaluasi}";
    }

    private function cplContext(MataKuliah $mk): array
    {
        if (! $mk->kurikulum_id) {
            return [];
        }

        return Cpl::query()
            ->where('kurikulum_id', $mk->kurikulum_id)
            ->get(['kode', 'deskripsi'])
            ->map(fn($c) => ['kode' => $c->kode, 'deskripsi' => $c->deskripsi])
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
