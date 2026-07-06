@php
    /** @var \App\Models\RpsVersion $rps */
    /** @var array $konteks */
    $sksTeori = $mk->sks_teori ?? 0;
    $sksPraktik = $mk->sks_praktik ?? 0;
    $sksTotal = $mk?->sks ?? ((int) $sksTeori + (int) $sksPraktik);

    $universitas = data_get($konteks, 'universitas.nama');
    $fakultas    = data_get($konteks, 'fakultas.nama');
    $prodi       = data_get($konteks, 'prodi.nama');
    $prodiKode   = data_get($konteks, 'prodi.kode');
    $pengampu    = $konteks['pengampu']         ?? [];
    $prasyarat   = $konteks['prasyarat']        ?? null;
    $bahanKajian = $konteks['bahan_kajian']     ?? [];
    $pUtama      = $konteks['pustaka_utama']    ?? [];
    $pPendukung  = $konteks['pustaka_pendukung'] ?? [];
    $cpmkList    = $konteks['cpmk_list']        ?? [];
    $subCpmkList = $konteks['sub_cpmk_list']    ?? [];
    $matriks     = $konteks['matriks_korelasi'] ?? ['cpl' => [], 'baris' => []];

    // Kelompokkan komponen penilaian per minggu (untuk render baris tugas / UTS / UAS).
    $komponenByMinggu = collect($komponen)->groupBy(fn($k) => (int) ($k->minggu_ke ?? 0));

    // Deteksi baris minggu khusus (UTS/UAS) untuk render sebagai baris merentang.
    $ujianMap = [];
    foreach ($komponen as $k) {
        $jenis = strtolower((string) ($k->jenis ?? ''));
        if (in_array($jenis, ['uts', 'uas'], true) && $k->minggu_ke) {
            $ujianMap[(int) $k->minggu_ke] = strtoupper($jenis);
        }
    }

    // Gabung label minggu berurutan yang memakai sub_cpmk_id sama (contoh "1,2").
    $mingguRows = [];
    $prev = null;
    foreach ($minggu as $m) {
        $subKode = $m->subCpmk?->kode;
        if ($prev
            && $prev['sub'] === $subKode
            && (int) $m->minggu_ke === $prev['end'] + 1
            && ! isset($ujianMap[(int) $m->minggu_ke])
            && ! isset($ujianMap[$prev['end']])) {
            $prev['end']    = (int) $m->minggu_ke;
            $prev['label']  = $prev['start'] === $prev['end']
                ? (string) $prev['start']
                : $prev['start'] . ',' . $prev['end'];
            $mingguRows[count($mingguRows) - 1] = $prev;
        } else {
            $entry = [
                'start' => (int) $m->minggu_ke,
                'end'   => (int) $m->minggu_ke,
                'label' => (string) $m->minggu_ke,
                'sub'   => $subKode,
                'row'   => $m,
            ];
            $mingguRows[] = $entry;
            $prev = $entry;
        }
    }

    $formatWaktu = function ($waktu) {
        return app(\App\Services\Rps\RpsPrintContext::class)->formatEstimasi($waktu);
    };

    $formatKriteria = function ($teks) {
        return app(\App\Services\Rps\RpsPrintContext::class)->formatKriteria($teks);
    };

    $angka = fn($v) => $v === null || $v === '' ? '—' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RPS {{ $rps->kode_mk }} — v{{ $rps->versi }}</title>
    <style>
        :root { --ink:#111827; --muted:#4b5563; --line:#334155; --thin:#94a3b8; --fill:#f1f5f9; --brand:#1d4ed8; }
        * { box-sizing: border-box; }
        body {
            font-family: "Times New Roman", Georgia, serif;
            color: var(--ink); margin: 0; background: #e5e7eb; font-size: 11px; line-height: 1.35;
        }
        .sheet { background: #fff; max-width: 1200px; margin: 24px auto; padding: 24px 28px 40px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        .toolbar { max-width: 1200px; margin: 16px auto 0; text-align: right; }
        .btn { display: inline-block; background: var(--brand); color: #fff; border: 0; padding: 8px 16px; border-radius: 6px; font-size: 13px; cursor: pointer; text-decoration: none; font-family: -apple-system, Segoe UI, sans-serif; }
        .btn.ghost { background: #fff; color: var(--ink); border: 1px solid var(--thin); margin-right: 8px; }
        table.kpt { width: 100%; border-collapse: collapse; margin: 0; }
        table.kpt th, table.kpt td { border: 1px solid var(--line); padding: 4px 6px; vertical-align: top; text-align: left; }
        table.kpt th { background: var(--fill); font-weight: 700; text-align: center; }
        table.kpt td.c, table.kpt th.c, .c { text-align: center; }
        table.kpt td.r, table.kpt th.r, .r { text-align: right; }
        .fill { background: var(--fill); font-weight: 700; }
        table.kpt td.band, .band { background: #fef3c7; font-weight: 700; text-align: center; }
        .muted { color: var(--muted); }
        .small { font-size: 10px; }
        .xs { font-size: 9.5px; }
        .bold { font-weight: 700; }
        .kop { display: table; width: 100%; border: 1px solid var(--line); border-bottom: 0; }
        .kop-row { display: table-row; }
        .kop-logo { display: table-cell; width: 90px; text-align: center; vertical-align: middle; padding: 8px; border-right: 1px solid var(--line); font-size: 9px; color: var(--muted); }
        .kop-inst { display: table-cell; text-align: center; vertical-align: middle; padding: 6px; }
        .kop-doc  { display: table-cell; width: 200px; padding: 6px; border-left: 1px solid var(--line); font-size: 10px; }
        .kop-inst .u { font-weight: 700; font-size: 14px; }
        .kop-inst .f { font-size: 12px; }
        .kop-inst .p { font-size: 12px; }
        ul.tight { margin: 0; padding-left: 18px; }
        ul.tight li { margin: 0; padding: 0; }
        .stack > * + * { margin-top: 4px; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; margin: 0; max-width: none; padding: 0; }
            @page { size: A4 landscape; margin: 12mm; }
            tr, table { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="javascript:history.back()" class="btn ghost">← Kembali</a>
        <button class="btn" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>

    <div class="sheet">
        <div class="kop">
            <div class="kop-row">
                <div class="kop-logo">LOGO<br>INSTITUSI</div>
                <div class="kop-inst">
                    @if($universitas)<div class="u">{{ $universitas }}</div>@endif
                    @if($fakultas)<div class="f">{{ $fakultas }}</div>@endif
                    @if($prodi)<div class="p">Program Studi: {{ $prodi }}@if($prodiKode) ({{ $prodiKode }})@endif</div>@endif
                    @if(! $universitas && ! $fakultas && ! $prodi)
                        <div class="u">{{ $institusi->nama ?? 'Institusi' }}</div>
                    @endif
                </div>
                <div class="kop-doc">
                    <div class="bold">Kode Dokumen</div>
                    <div>{{ $rps->kode_dokumen ?? '—' }}</div>
                </div>
            </div>
        </div>

        <table class="kpt" style="border-top:0;">
            <tr>
                <th colspan="7" style="background:#e2e8f0;">RENCANA PEMBELAJARAN SEMESTER</th>
            </tr>
            <tr>
                <th style="width:15%;">MATA KULIAH (MK)</th>
                <th style="width:12%;">KODE</th>
                <th style="width:15%;">Rumpun MK</th>
                <th style="width:15%;">BOBOT (sks)</th>
                <th style="width:9%;">SEMESTER</th>
                <th style="width:16%;">Tgl Penyusunan</th>
                <th style="width:18%;">Sifat</th>
            </tr>
            <tr>
                <td>{{ $mk->nama ?? $rps->kode_mk }}</td>
                <td>{{ $rps->kode_mk }}</td>
                <td>{{ $mk->rumpun ?? '—' }}</td>
                <td class="c">T={{ (int) $sksTeori }} &nbsp; P={{ (int) $sksPraktik }} &nbsp; (Σ {{ $sksTotal }})</td>
                <td class="c">{{ $mk->semester ?? '—' }}</td>
                <td class="c">{{ optional($rps->tanggal_penyusunan)->format('d-m-Y') ?? '—' }}</td>
                <td>{{ $mk?->sifat ? ucfirst($mk->sifat) : '—' }}</td>
            </tr>

            <tr>
                <td class="fill" rowspan="2" style="vertical-align:middle;">OTORISASI / PENGESAHAN</td>
                <td colspan="2" class="c fill">Dosen Pengembang RPS</td>
                <td colspan="2" class="c fill">Koordinator RMK</td>
                <td colspan="2" class="c fill">Ka PRODI</td>
            </tr>
            <tr>
                <td colspan="2" class="c" style="height:60px; vertical-align:bottom;">&nbsp;</td>
                <td colspan="2" class="c" style="height:60px; vertical-align:bottom;">&nbsp;</td>
                <td colspan="2" class="c" style="height:60px; vertical-align:bottom;">&nbsp;</td>
            </tr>

            <tr>
                <td class="fill" rowspan="6" style="vertical-align:middle;">Capaian Pembelajaran (CP)</td>
                <td colspan="6" class="fill">CPL-PRODI yang dibebankan pada MK</td>
            </tr>
            <tr>
                <td colspan="6">
                    @if($cplDiampu->isEmpty())
                        <span class="muted">Belum ada CPL tertaut pada RPS ini.</span>
                    @else
                        <div class="stack">
                            @foreach($cplDiampu as $cpl)
                                <div><span class="bold">{{ $cpl->kode }}</span> &nbsp; {{ $cpl->deskripsi }}</div>
                            @endforeach
                        </div>
                    @endif
                </td>
            </tr>
            <tr>
                <td colspan="6" class="fill">Capaian Pembelajaran Mata Kuliah (CPMK)</td>
            </tr>
            <tr>
                <td colspan="6">
                    @if(empty($cpmkList))
                        <span class="muted">Belum ada CPMK.</span>
                    @else
                        <div class="stack">
                            @foreach($cpmkList as $c)
                                <div>
                                    <span class="bold">{{ $c['kode'] }}</span> &nbsp;
                                    {{ $c['deskripsi'] }}
                                    @if(! empty($c['kontribusi_persen']))<span class="bold"> ({{ $angka($c['kontribusi_persen']) }}%)</span>@endif
                                    @if(! empty($c['bloom']))<span class="muted"> {{ $c['bloom'] }}</span>@endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </td>
            </tr>
            <tr>
                <td colspan="6" class="fill">Kemampuan akhir tiap tahapan belajar (Sub-CPMK)</td>
            </tr>
            <tr>
                <td colspan="6">
                    @if(empty($subCpmkList))
                        <span class="muted">Belum ada Sub-CPMK.</span>
                    @else
                        <div class="stack">
                            @foreach($subCpmkList as $s)
                                <div>
                                    <span class="bold">{{ $s['kode'] }}</span> &nbsp;
                                    {{ $s['deskripsi'] }}
                                    @if(! empty($s['kontribusi_persen']))<span class="bold"> ({{ $angka($s['kontribusi_persen']) }}%)</span>@endif
                                    @if(! empty($s['bloom']))<span class="muted"> {{ $s['bloom'] }}</span>@endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </td>
            </tr>

            @if(! empty($matriks['baris']))
                <tr>
                    <td class="fill" style="vertical-align:middle;">Korelasi Sub-CPMK terhadap CPL</td>
                    <td colspan="6" style="padding:0;">
                        <table class="kpt" style="border:0; margin:0;">
                            <tr>
                                <th style="border-top:0; border-left:0;">Sub-CPMK</th>
                                @foreach($matriks['cpl'] as $c)
                                    <th class="c" style="border-top:0;">{{ $c['kode'] }} (%)</th>
                                @endforeach
                                <th class="c" style="border-top:0; border-right:0;">Bobot Penilaian (%)</th>
                            </tr>
                            @foreach($matriks['baris'] as $baris)
                                <tr>
                                    <td class="bold" style="border-left:0;">{{ $baris['sub_cpmk'] }}</td>
                                    @foreach($matriks['cpl'] as $c)
                                        <td class="c">{{ $baris['bobot_per_cpl'][$c['kode']] !== null ? $angka($baris['bobot_per_cpl'][$c['kode']]) : '' }}</td>
                                    @endforeach
                                    <td class="c" style="border-right:0;">{{ $baris['bobot_nilai'] !== null ? $angka($baris['bobot_nilai']) : '' }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
            @endif

            <tr>
                <td class="fill" style="vertical-align:middle;">Diskripsi Singkat MK</td>
                <td colspan="6">{{ $mk?->deskripsi_singkat ?? '—' }}</td>
            </tr>

            <tr>
                <td class="fill" style="vertical-align:middle;">Bahan Kajian: Materi pembelajaran</td>
                <td colspan="6">
                    @if(empty($bahanKajian))
                        <span class="muted">—</span>
                    @else
                        <ol class="tight">
                            @foreach($bahanKajian as $bk)
                                <li>
                                    <span class="bold">{{ $bk['nama'] }}</span>
                                    @if(! empty($bk['deskripsi'])) — {{ $bk['deskripsi'] }} @endif
                                    @if(! empty($bk['keterampilan']))
                                        <ul class="tight" style="list-style-type: circle;">
                                            @foreach($bk['keterampilan'] as $k)
                                                <li>{{ $k }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </td>
            </tr>

            <tr>
                <td class="fill" rowspan="4" style="vertical-align:middle;">Pustaka</td>
                <td colspan="6" class="fill">Utama:</td>
            </tr>
            <tr>
                <td colspan="6">
                    @if(empty($pUtama))
                        <span class="muted">—</span>
                    @else
                        <ol class="tight">
                            @foreach($pUtama as $p)
                                <li>{{ $p }}</li>
                            @endforeach
                        </ol>
                    @endif
                </td>
            </tr>
            <tr>
                <td colspan="6" class="fill">Pendukung:</td>
            </tr>
            <tr>
                <td colspan="6">
                    @if(empty($pPendukung))
                        <span class="muted">—</span>
                    @else
                        <ol class="tight">
                            @foreach($pPendukung as $p)
                                <li>{{ $p }}</li>
                            @endforeach
                        </ol>
                    @endif
                </td>
            </tr>

            <tr>
                <td class="fill" style="vertical-align:middle;">Dosen Pengampu</td>
                <td colspan="6">
                    @if(empty($pengampu))
                        <span class="muted">&nbsp;</span>
                    @else
                        {{ collect($pengampu)->pluck('nama')->implode(', ') }}
                    @endif
                </td>
            </tr>

            <tr>
                <td class="fill" style="vertical-align:middle;">Matakuliah syarat</td>
                <td colspan="6">
                    @if($prasyarat)
                        <span class="bold">{{ $prasyarat['kode'] }}</span>
                        @if(! empty($prasyarat['nama'])) — {{ $prasyarat['nama'] }}@endif
                    @else
                        <span class="muted">—</span>
                    @endif
                </td>
            </tr>
        </table>

        <div style="height:8px;"></div>
        <table class="kpt">
            <thead>
                <tr>
                    <th rowspan="2" style="width:4%;">Mg Ke-</th>
                    <th rowspan="2" style="width:16%;">Kemampuan akhir tiap tahapan belajar (Sub-CPMK)</th>
                    <th colspan="2">Penilaian</th>
                    <th colspan="2">Bentuk Pembelajaran; Metode Pembelajaran; Penugasan Mahasiswa; [Estimasi Waktu]</th>
                    <th rowspan="2" style="width:20%;">Materi Pembelajaran [Pustaka]</th>
                    <th rowspan="2" style="width:7%;">Bobot Penilaian (%)</th>
                </tr>
                <tr>
                    <th style="width:12%;">Indikator</th>
                    <th style="width:12%;">Kriteria &amp; Bentuk</th>
                    <th style="width:14%;">Luring</th>
                    <th style="width:14%;">Daring</th>
                </tr>
                <tr class="xs">
                    <th>(1)</th><th>(2)</th><th>(3)</th><th>(4)</th><th>(5)</th><th>(6)</th><th>(7)</th><th>(8)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($mingguRows as $entry)
                    @php $m = $entry['row']; $waktu = $formatWaktu($m->estimasi_waktu); @endphp

                    @if(isset($ujianMap[$entry['start']]) && $entry['start'] === $entry['end'])
                        <tr>
                            <td class="c bold">{{ $entry['label'] }}</td>
                            <td colspan="7" class="band">
                                {{ $ujianMap[$entry['start']] === 'UTS' ? 'ETS / Evaluasi Tengah Semester' : 'EAS / Evaluasi Akhir Semester' }}
                                @if($m->indikator) — {{ $m->indikator }}@endif
                            </td>
                        </tr>
                    @else
                        <tr class="small">
                            <td class="c bold" style="vertical-align:middle;">{{ $entry['label'] }}</td>
                            <td>
                                @if($m->subCpmk)
                                    <div class="bold">{{ $m->subCpmk->kode }}</div>
                                    @if($m->subCpmk->deskripsi)
                                        <div>{{ $m->subCpmk->deskripsi }}</div>
                                    @endif
                                    @php
                                        $bloom = $m->subCpmk->taksonomi_kode;
                                        $bloomStr = null;
                                        if (is_array($bloom) && ! empty($bloom)) {
                                            $bloomStr = '[' . implode(',', $bloom) . ']';
                                        } elseif (is_string($bloom) && $bloom !== '') {
                                            $bloomStr = '[' . $bloom . ']';
                                        }
                                    @endphp
                                    @if($bloomStr)<div class="muted xs">{{ $bloomStr }}</div>@endif
                                    @if($m->subCpmk->cpmk)
                                        <div class="muted xs">CPMK {{ $m->subCpmk->cpmk->kode }}@if($m->subCpmk->cpmk->deskripsi): {{ $m->subCpmk->cpmk->deskripsi }}@endif</div>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $m->indikator ?? '—' }}</td>
                            <td style="white-space:pre-line;">{{ $formatKriteria($m->teknik_kriteria_penilaian) ?: '—' }}</td>
                            <td>
                                @if($m->bentuk_luring){{ $m->bentuk_luring }}@else<span class="muted">—</span>@endif
                                @if($m->metode_pembelajaran)<div class="xs muted">Metode: {{ $m->metode_pembelajaran }}</div>@endif
                                @if($waktu !== '')<div class="xs">{{ $waktu }}</div>@endif
                            </td>
                            <td>
                                @if($m->bentuk_daring){{ $m->bentuk_daring }}@else<span class="muted">—</span>@endif
                                @if($m->pengalaman_belajar)<div class="xs muted">Penugasan: {{ $m->pengalaman_belajar }}</div>@endif
                            </td>
                            <td>{{ $m->materi_pustaka ?? '—' }}</td>
                            <td class="c">{{ $angka($m->bobot_penilaian) }}</td>
                        </tr>

                        @php
                            $tasksHere = collect();
                            for ($mm = $entry['start']; $mm <= $entry['end']; $mm++) {
                                $tasksHere = $tasksHere->merge($komponenByMinggu->get($mm, collect()));
                            }
                            $tasksHere = $tasksHere->filter(function ($k) {
                                $j = strtolower((string) ($k->jenis ?? ''));
                                return ! in_array($j, ['uts', 'uas'], true);
                            })->values();
                        @endphp
                        @if($tasksHere->isNotEmpty())
                            <tr class="xs">
                                <td class="c muted">&nbsp;</td>
                                <td colspan="7">
                                    @foreach($tasksHere as $t)
                                        <div>
                                            <span class="bold">{{ $t->nama }}</span>
                                            @if($t->instrumen) — {{ $t->instrumen }}@endif
                                            @if($t->minggu_ke) <span class="muted">(minggu {{ $t->minggu_ke }})</span>@endif
                                            @if($t->bobot_persen !== null) <span class="muted">· bobot {{ $angka($t->bobot_persen) }}%</span>@endif
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                        @endif
                    @endif
                @empty
                    <tr><td colspan="8" class="c muted">Belum ada rencana mingguan.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div style="height:16px;"></div>
        <h3 style="font-family:-apple-system,Segoe UI,sans-serif; font-size:12px; margin:0 0 6px;">Komponen Penilaian</h3>
        <table class="kpt">
            <thead>
                <tr>
                    <th style="width:26%;">Komponen</th>
                    <th style="width:12%;">Jenis</th>
                    <th style="width:22%;">Instrumen</th>
                    <th style="width:14%;">Sub-CPMK</th>
                    <th style="width:8%;">Minggu</th>
                    <th style="width:10%;">Bobot (%)</th>
                </tr>
            </thead>
            <tbody>
                @php $totalBobot = 0; @endphp
                @forelse($komponen as $k)
                    @php $totalBobot += (float) $k->bobot_persen; @endphp
                    <tr class="small">
                        <td>{{ $k->nama }}</td>
                        <td>{{ $k->jenis ?? '—' }}</td>
                        <td>{{ $k->instrumen ?? '—' }}</td>
                        <td>
                            @if($k->subCpmk)
                                <span class="bold">{{ $k->subCpmk->kode }}</span>
                                @if($k->subCpmk->deskripsi)<div class="xs">{{ $k->subCpmk->deskripsi }}</div>@endif
                                @if($k->subCpmk->cpmk)
                                    <div class="muted xs">CPMK {{ $k->subCpmk->cpmk->kode }}@if($k->subCpmk->cpmk->deskripsi): {{ $k->subCpmk->cpmk->deskripsi }}@endif</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="c">{{ $k->minggu_ke ?? '—' }}</td>
                        <td class="c">{{ $angka($k->bobot_persen) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="c muted">Belum ada komponen penilaian.</td></tr>
                @endforelse
                @if($komponen->isNotEmpty())
                    <tr class="fill">
                        <td colspan="5">Total</td>
                        <td class="c">{{ $angka($totalBobot) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>

        @php $adaRubrik = $komponen->contains(fn($k) => $k->rubrik && $k->rubrik->kriteria->isNotEmpty()); @endphp
        @if($adaRubrik)
            <div style="height:16px;"></div>
            <h3 style="font-family:-apple-system,Segoe UI,sans-serif; font-size:12px; margin:0 0 6px;">Rubrik Penilaian</h3>
            @foreach($komponen as $k)
                @php $r = $k->rubrik; @endphp
                @if($r && $r->kriteria->isNotEmpty())
                    @php
                        $labels = is_array($r->label_skala) ? array_values($r->label_skala) : [];
                        $levels = $r->jumlah_level_skala ?: (count($labels) ?: 4);
                    @endphp
                    <div class="small" style="margin:12px 0 4px;">
                        <span class="bold">{{ $k->nama }}</span>
                        <span class="muted"> — rubrik {{ $r->jenis }}</span>
                    </div>
                    <table class="kpt">
                        <thead>
                            <tr>
                                <th style="width:22%;">Kriteria</th>
                                <th style="width:8%;">Bobot (%)</th>
                                @for($i = 0; $i < $levels; $i++)
                                    <th>{{ $labels[$i] ?? ('Level ' . ($i + 1)) }}</th>
                                @endfor
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($r->kriteria->sortBy('urutan') as $kr)
                                @php $desk = is_array($kr->deskriptor) ? array_values($kr->deskriptor) : []; @endphp
                                <tr class="small">
                                    <td>{{ $kr->kriteria }}</td>
                                    <td class="c">{{ $angka($kr->bobot) }}</td>
                                    @for($i = 0; $i < $levels; $i++)
                                        <td>{{ $desk[$i] ?? '—' }}</td>
                                    @endfor
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            @endforeach
        @endif

        <p class="muted xs" style="margin-top:28px; text-align:right;">
            Dokumen mengikuti template Panduan Penyusunan KPT 2024 Direktorat Belmawa · Dicetak {{ now()->format('d M Y H:i') }} · Versi {{ $rps->versi }}
        </p>
    </div>
</body>
</html>
