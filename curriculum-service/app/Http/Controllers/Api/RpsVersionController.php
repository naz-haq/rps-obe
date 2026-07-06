<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AppliesSorting;
use App\Http\Controllers\Controller;
use App\Http\Resources\RpsVersionResource;
use App\Models\Institusi;
use App\Models\MataKuliah;
use App\Models\Rubrik;
use App\Models\RpsVersion;
use App\Services\Rps\RpsDocxExporter;
use App\Services\Rps\RpsPrintContext;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\IOFactory;

class RpsVersionController extends Controller
{
    use AppliesSorting;

    public function index(Request $request)
    {
        $query = RpsVersion::query()->withCount(['minggu', 'komponenPenilaian']);

        if ($request->filled('institusi_id')) {
            $query->where('institusi_id', $request->integer('institusi_id'));
        }
        if ($request->filled('kode_mk')) {
            $query->where('kode_mk', $request->string('kode_mk'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $this->applySort($query, $request, ['kode_mk', 'versi', 'status', 'created_at'], 'created_at', 'desc');

        return RpsVersionResource::collection($query->paginate($request->integer('per_page', 15)));
    }

    /** Struktur RPS committed (minggu + rantai Sub-CPMK/CPMK, komponen penilaian). */
    public function show(RpsVersion $rpsVersion)
    {
        $rpsVersion->load([
            'minggu.subCpmk.cpmk',
            'minggu.subCpmk.indikator',
            'komponenPenilaian.subCpmk.cpmk',
            'komponenPenilaian.rubrik.kriteria',
        ])->loadCount(['minggu', 'komponenPenilaian']);

        $ctx = app(RpsPrintContext::class);
        $minggu = $rpsVersion->minggu
            ->sortBy('minggu_ke')
            ->map(fn($m) => [
                'minggu_ke'            => $m->minggu_ke,
                'sub_cpmk'             => $m->subCpmk?->kode,
                'sub_cpmk_deskripsi'   => $m->subCpmk?->deskripsi,
                'sub_cpmk_bloom'       => $ctx->bloomTag($m->subCpmk?->taksonomi_kode),
                'cpmk'                 => $m->subCpmk?->cpmk?->kode,
                'cpmk_deskripsi'       => $m->subCpmk?->cpmk?->deskripsi,
                'indikator'            => $m->indikator,
                'kriteria_penilaian'   => $ctx->formatKriteria($m->teknik_kriteria_penilaian),
                'metode_pembelajaran'  => $m->metode_pembelajaran,
                'bentuk_luring'        => $m->bentuk_luring,
                'bentuk_daring'        => $m->bentuk_daring,
                'pengalaman_belajar'   => $m->pengalaman_belajar,
                'materi_pustaka'       => $m->materi_pustaka,
                'estimasi_waktu'       => is_array($m->estimasi_waktu)
                    ? array_merge($m->estimasi_waktu, ['teks' => $ctx->formatEstimasi($m->estimasi_waktu)])
                    : $m->estimasi_waktu,
                'bobot_penilaian'      => $m->bobot_penilaian,
            ])->values();

        $komponen = $rpsVersion->komponenPenilaian
            ->map(fn($k) => [
                'nama'               => $k->nama,
                'jenis'              => $k->jenis,
                'instrumen'          => $k->instrumen,
                'bobot_persen'       => $k->bobot_persen,
                'sub_cpmk'           => $k->subCpmk?->kode,
                'sub_cpmk_deskripsi' => $k->subCpmk?->deskripsi,
                'cpmk'               => $k->subCpmk?->cpmk?->kode,
                'cpmk_deskripsi'     => $k->subCpmk?->cpmk?->deskripsi,
                'minggu_ke'          => $k->minggu_ke,
                'rubrik'             => $this->rubrikArray($k->rubrik),
            ])->values();

        $konteks = app(RpsPrintContext::class)->build($rpsVersion);

        return response()->json([
            'data' => [
                'rps'      => new RpsVersionResource($rpsVersion),
                'minggu'   => $minggu,
                'komponen' => $komponen,
                'konteks'  => $konteks,
            ],
        ]);
    }

    /**
     * Traceability OBE: rantai CPL <- CPMK <- Sub-CPMK <- Minggu untuk RPS ini.
     * Menampilkan pemetaan tiap Sub-CPMK ke CPMK & CPL yang diembannya.
     */
    public function traceability(RpsVersion $rpsVersion)
    {
        $rpsVersion->load(['minggu.subCpmk.cpmk.cpl']);

        $rantai = [];
        foreach ($rpsVersion->minggu->sortBy('minggu_ke') as $m) {
            $sub = $m->subCpmk;
            if (! $sub) {
                continue;
            }
            $kode = $sub->kode;
            if (! isset($rantai[$kode])) {
                $rantai[$kode] = [
                    'sub_cpmk'  => $sub->kode,
                    'deskripsi' => $sub->deskripsi,
                    'cpmk'      => $sub->cpmk?->kode,
                    'cpl'       => $sub->cpmk ? $sub->cpmk->cpl->pluck('kode')->values() : [],
                    'minggu'    => [],
                ];
            }
            $rantai[$kode]['minggu'][] = $m->minggu_ke;
        }

        $rantai = array_values($rantai);

        return response()->json([
            'data' => [
                'kode_mk'    => $rpsVersion->kode_mk,
                'versi'      => $rpsVersion->versi,
                'rantai'     => $rantai,
                'cpl_diampu' => collect($rantai)->flatMap(fn($r) => $r['cpl'])->unique()->values(),
            ],
        ]);
    }

    /** Dokumen RPS siap-cetak (HTML print-ready; user simpan sebagai PDF via browser). */
    public function cetak(RpsVersion $rpsVersion)
    {
        $rpsVersion->load([
            'minggu.subCpmk.cpmk',
            'minggu.subCpmk.indikator',
            'komponenPenilaian.subCpmk.cpmk',
            'komponenPenilaian.rubrik.kriteria',
        ]);

        $mk = MataKuliah::where('kode_mk', $rpsVersion->kode_mk)
            ->where('institusi_id', $rpsVersion->institusi_id)
            ->first();
        $institusi = Institusi::find($rpsVersion->institusi_id);

        $minggu = $rpsVersion->minggu->sortBy('minggu_ke')->values();
        $komponen = $rpsVersion->komponenPenilaian->values();

        // Rantai CPL untuk footer keterlacakan.
        $rpsVersion->loadMissing(['minggu.subCpmk.cpmk.cpl']);
        $cplDiampu = $rpsVersion->minggu
            ->flatMap(fn($m) => $m->subCpmk?->cpmk ? $m->subCpmk->cpmk->cpl : collect())
            ->unique('id')
            ->values();

        $konteks = app(RpsPrintContext::class)->build($rpsVersion);

        return view('rps.cetak', [
            'rps'        => $rpsVersion,
            'mk'         => $mk,
            'institusi'  => $institusi,
            'minggu'     => $minggu,
            'komponen'   => $komponen,
            'cplDiampu'  => $cplDiampu,
            'konteks'    => $konteks,
        ]);
    }

    /** Ekspor RPS sebagai dokumen Word (.docx) asli via PhpWord. */
    public function unduhDocx(RpsVersion $rpsVersion, RpsDocxExporter $exporter)
    {
        $phpWord = $exporter->build($rpsVersion);
        $writer = IOFactory::createWriter($phpWord, 'Word2007');

        $namaFile = sprintf(
            'RPS_%s_v%s.docx',
            preg_replace('/[^A-Za-z0-9_-]+/', '', (string) $rpsVersion->kode_mk) ?: 'MK',
            $rpsVersion->versi
        );

        // Tulis ke file sementara agar biner utuh (hindari kontaminasi output buffer).
        $tmp = tempnam(sys_get_temp_dir(), 'rps_docx_');
        $writer->save($tmp);

        return response()
            ->download($tmp, $namaFile, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])
            ->deleteFileAfterSend(true);
    }

    /**
     * Ubah relasi Rubrik (+kriteria) menjadi array untuk respons JSON.
     */
    private function rubrikArray(?Rubrik $rubrik): ?array
    {
        if (! $rubrik) {
            return null;
        }

        return [
            'jenis'              => $rubrik->jenis,
            'jumlah_level_skala' => $rubrik->jumlah_level_skala,
            'label_skala'        => $rubrik->label_skala,
            'kriteria'           => $rubrik->kriteria
                ->sortBy('urutan')
                ->map(fn($k) => [
                    'kriteria'   => $k->kriteria,
                    'bobot'      => $k->bobot,
                    'deskriptor' => $k->deskriptor,
                ])->values(),
        ];
    }
}
