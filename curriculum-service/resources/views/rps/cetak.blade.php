@php
    /** @var \App\Models\RpsVersion $rps */
    $sksTeori = $mk->sks_teori ?? null;
    $sksPraktik = $mk->sks_praktik ?? null;
    $sksTotal = $mk?->sks ?? (($sksTeori ?? 0) + ($sksPraktik ?? 0));
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RPS {{ $rps->kode_mk }} — v{{ $rps->versi }}</title>
    <style>
        :root { --ink:#1e293b; --muted:#64748b; --line:#cbd5e1; --brand:#2563eb; }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            color: var(--ink); margin: 0; background: #f1f5f9; font-size: 12px; line-height: 1.5;
        }
        .sheet {
            background: #fff; max-width: 900px; margin: 24px auto; padding: 32px 36px;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
        }
        .toolbar { max-width: 900px; margin: 16px auto 0; text-align: right; }
        .btn {
            display: inline-block; background: var(--brand); color: #fff; border: 0;
            padding: 8px 16px; border-radius: 8px; font-size: 13px; cursor: pointer; text-decoration: none;
        }
        .btn.ghost { background: #fff; color: var(--ink); border: 1px solid var(--line); margin-right: 8px; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); margin: 24px 0 8px; border-bottom: 2px solid var(--line); padding-bottom: 4px; }
        .head { text-align: center; border-bottom: 3px double var(--line); padding-bottom: 12px; margin-bottom: 8px; }
        .head .inst { font-size: 14px; font-weight: 700; }
        .head .sub { color: var(--muted); }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { border: 1px solid var(--line); padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f8fafc; font-weight: 600; }
        .meta td:nth-child(odd) { background: #f8fafc; font-weight: 600; width: 22%; }
        .center { text-align: center; }
        .muted { color: var(--muted); }
        .badge { display: inline-block; background: #eff6ff; color: var(--brand); border-radius: 6px; padding: 1px 7px; font-size: 11px; margin: 1px; }
        .totrow td { font-weight: 700; background: #f8fafc; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; margin: 0; max-width: none; padding: 0; }
            h2 { break-after: avoid; }
            tr { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="javascript:history.back()" class="btn ghost">← Kembali</a>
        <button class="btn" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>

    <div class="sheet">
        <div class="head">
            <div class="inst">{{ $institusi->nama ?? 'Institusi' }}</div>
            <div class="sub">Rencana Pembelajaran Semester (RPS)</div>
        </div>

        <h1>{{ $mk->nama ?? $rps->kode_mk }}</h1>
        <p class="muted">Kode: {{ $rps->kode_mk }} · Versi {{ $rps->versi }} · Status {{ ucfirst($rps->status) }} · Bahasa {{ strtoupper($rps->bahasa ?? 'id') }}</p>

        <h2>Identitas Mata Kuliah</h2>
        <table class="meta">
            <tr>
                <td>Kode Mata Kuliah</td><td>{{ $rps->kode_mk }}</td>
                <td>SKS</td><td>{{ $sksTotal }} @if($sksTeori !== null || $sksPraktik !== null)(T:{{ $sksTeori ?? 0 }} / P:{{ $sksPraktik ?? 0 }})@endif</td>
            </tr>
            <tr>
                <td>Nama Mata Kuliah</td><td>{{ $mk->nama ?? '—' }}</td>
                <td>Semester</td><td>{{ $mk->semester ?? '—' }}</td>
            </tr>
            <tr>
                <td>Rumpun</td><td>{{ $mk->rumpun ?? '—' }}</td>
                <td>Sifat</td><td>{{ $mk?->sifat ? ucfirst($mk->sifat) : '—' }}</td>
            </tr>
            <tr>
                <td>Koordinator MK</td><td>{{ $rps->koordinator_mk ?? '—' }}</td>
                <td>Tanggal Penyusunan</td><td>{{ optional($rps->tanggal_penyusunan)->format('d M Y') ?? '—' }}</td>
            </tr>
        </table>

        @if($mk?->deskripsi_singkat)
            <h2>Deskripsi Mata Kuliah</h2>
            <p>{{ $mk->deskripsi_singkat }}</p>
        @endif

        <h2>CPL yang Diampu</h2>
        @if($cplDiampu->isEmpty())
            <p class="muted">Belum ada CPL tertaut pada RPS ini.</p>
        @else
            @foreach($cplDiampu as $cpl)
                <div><span class="badge">{{ $cpl->kode }}</span> {{ $cpl->deskripsi }}</div>
            @endforeach
        @endif

        <h2>Rencana Pembelajaran Mingguan</h2>
        <table>
            <thead>
                <tr>
                    <th class="center" style="width:4%">Mg</th>
                    <th style="width:10%">Sub-CPMK (Kemampuan akhir)</th>
                    <th style="width:13%">Indikator</th>
                    <th style="width:13%">Kriteria &amp; Teknik Penilaian</th>
                    <th style="width:16%">Bentuk &amp; Metode Pembelajaran</th>
                    <th style="width:14%">Pengalaman Belajar</th>
                    <th style="width:13%">Materi &amp; Pustaka</th>
                    <th style="width:10%">Estimasi Waktu</th>
                    <th class="center" style="width:7%">Bobot (%)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($minggu as $m)
                    @php
                        $bentuk = collect([
                            $m->metode_pembelajaran,
                            $m->bentuk_luring ? 'Luring: ' . $m->bentuk_luring : null,
                            $m->bentuk_daring ? 'Daring: ' . $m->bentuk_daring : null,
                        ])->filter()->implode('; ');
                        $waktu = is_array($m->estimasi_waktu) ? ($m->estimasi_waktu['teks'] ?? '') : (string) $m->estimasi_waktu;
                    @endphp
                    <tr>
                        <td class="center">{{ $m->minggu_ke }}</td>
                        <td>{{ $m->subCpmk?->kode ?? '—' }}</td>
                        <td>{{ $m->indikator ?? '—' }}</td>
                        <td>{{ $m->teknik_kriteria_penilaian ?? '—' }}</td>
                        <td>{{ $bentuk !== '' ? $bentuk : '—' }}</td>
                        <td>{{ $m->pengalaman_belajar ?? '—' }}</td>
                        <td>{{ $m->materi_pustaka ?? '—' }}</td>
                        <td>{{ $waktu !== '' ? $waktu : '—' }}</td>
                        <td class="center">{{ $m->bobot_penilaian !== null ? rtrim(rtrim(number_format((float) $m->bobot_penilaian, 2), '0'), '.') : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="center muted">Belum ada rencana mingguan.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Komponen Penilaian</h2>
        <table>
            <thead>
                <tr>
                    <th style="width:26%">Komponen</th>
                    <th style="width:14%">Jenis</th>
                    <th style="width:22%">Instrumen</th>
                    <th style="width:14%">Sub-CPMK</th>
                    <th class="center" style="width:8%">Minggu</th>
                    <th class="center" style="width:10%">Bobot (%)</th>
                </tr>
            </thead>
            <tbody>
                @php $totalBobot = 0; @endphp
                @forelse($komponen as $k)
                    @php $totalBobot += (float) $k->bobot_persen; @endphp
                    <tr>
                        <td>{{ $k->nama }}</td>
                        <td>{{ $k->jenis ?? '—' }}</td>
                        <td>{{ $k->instrumen ?? '—' }}</td>
                        <td>{{ $k->subCpmk?->kode ?? '—' }}</td>
                        <td class="center">{{ $k->minggu_ke ?? '—' }}</td>
                        <td class="center">{{ rtrim(rtrim(number_format((float) $k->bobot_persen, 2), '0'), '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="center muted">Belum ada komponen penilaian.</td></tr>
                @endforelse
                @if($komponen->isNotEmpty())
                    <tr class="totrow">
                        <td colspan="5">Total</td>
                        <td class="center">{{ rtrim(rtrim(number_format($totalBobot, 2), '0'), '.') }}</td>
                    </tr>
                @endif
            </tbody>
        </table>

        @php $adaRubrik = $komponen->contains(fn($k) => $k->rubrik && $k->rubrik->kriteria->isNotEmpty()); @endphp
        @if($adaRubrik)
            <h2>Rubrik Penilaian</h2>
            @foreach($komponen as $k)
                @php $r = $k->rubrik; @endphp
                @if($r && $r->kriteria->isNotEmpty())
                    @php
                        $labels = is_array($r->label_skala) ? array_values($r->label_skala) : [];
                        $levels = $r->jumlah_level_skala ?: (count($labels) ?: 4);
                    @endphp
                    <h3 style="font-size:11px; margin:16px 0 6px;">{{ $k->nama }}
                        <span class="muted" style="font-weight:normal;">— rubrik {{ $r->jenis }}</span>
                    </h3>
                    <table>
                        <thead>
                            <tr>
                                <th style="width:22%">Kriteria</th>
                                <th class="center" style="width:8%">Bobot (%)</th>
                                @for($i = 0; $i < $levels; $i++)
                                    <th>{{ $labels[$i] ?? ('Level ' . ($i + 1)) }}</th>
                                @endfor
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($r->kriteria->sortBy('urutan') as $kr)
                                @php $desk = is_array($kr->deskriptor) ? array_values($kr->deskriptor) : []; @endphp
                                <tr>
                                    <td>{{ $kr->kriteria }}</td>
                                    <td class="center">{{ $kr->bobot !== null ? rtrim(rtrim(number_format((float) $kr->bobot, 2), '0'), '.') : '—' }}</td>
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

        <p class="muted" style="margin-top:28px; text-align:right;">
            Dicetak {{ now()->format('d M Y H:i') }}
        </p>
    </div>
</body>
</html>
