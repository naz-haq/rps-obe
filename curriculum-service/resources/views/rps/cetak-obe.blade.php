@php
    /** @var \App\Models\RpsVersion $rps */
    /** @var array $obe */
    $kop = $obe['kop'];
    $id = $obe['identitas'];
    $d = fn($v) => ($v === null || $v === '' || $v === []) ? '—' : $v;
    $angka = fn($v) => $v === null || $v === '' ? '—' : rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $cek = fn(bool $b) => $b ? '✓' : '–';
    $univ = $kop['universitas'] ?? null;
    $dataUri = fn(?string $f) => $f && is_file($f) ? 'data:' . mime_content_type($f) . ';base64,' . base64_encode(file_get_contents($f)) : null;
    $logo = $dataUri($kop['logo_file'] ?? null);
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RPS {{ $id['kode_mk'] }} — v{{ $rps->versi }}</title>
    <style>
        :root { --ink:#222; --muted:#666; --line:#9ca3af; --brand:#00a651; --soft:#f3f8ee; --sect:#e2f0d9; --warn:#fff2cc; }
        * { box-sizing: border-box; }
        body { font-family: Calibri, "Segoe UI", Arial, sans-serif; color: var(--ink); margin: 0; background: #e5e7eb; font-size: 10.5px; line-height: 1.35; }
        .sheet { background: #fff; max-width: 1200px; margin: 24px auto; padding: 24px 28px 40px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        .toolbar { max-width: 1200px; margin: 16px auto 0; text-align: right; }
        .btn { display: inline-block; background: #2563eb; color: #fff; border: 0; padding: 8px 16px; border-radius: 6px; font-size: 13px; cursor: pointer; text-decoration: none; }
        .btn.ghost { background: #fff; color: var(--ink); border: 1px solid var(--line); margin-right: 8px; }
        table { width: 100%; border-collapse: collapse; margin: 0 0 8px; }
        th, td { border: 1px solid var(--line); padding: 3px 5px; vertical-align: top; text-align: left; }
        th { background: var(--brand); color: #fff; font-weight: 700; text-align: center; vertical-align: middle; }
        td.lbl { background: var(--soft); font-weight: 700; width: 17%; }
        .c { text-align: center; } .r { text-align: right; } .b { font-weight: 700; }
        .muted { color: var(--muted); } .xs { font-size: 9px; }
        .pre { white-space: pre-line; }
        h2 { background: var(--sect); color: #0b5d2e; font-size: 11.5px; margin: 14px 0 4px; padding: 4px 6px; border-left: 4px solid var(--brand); }
        .kop td { vertical-align: middle; }
        .kop .logo { width: 90px; text-align: center; font-size: 9px; color: var(--muted); }
        .kop .inst { text-align: center; }
        .kop .inst .u { font-weight: 700; font-size: 14px; }
        .kop .form { text-align: center; font-weight: 700; }
        .kop .meta { width: 210px; font-size: 10px; }
        .judul { text-align: center; margin: 10px 0 6px; }
        .judul .t1 { font-size: 15px; font-weight: 700; }
        .judul .t2 { font-size: 12px; font-weight: 700; color: #0b5d2e; }
        .prinsip { background: var(--soft); border: 1px solid var(--line); padding: 5px 8px; margin-bottom: 8px; font-style: italic; }
        tr.ujian td { background: var(--warn); }
        tr.rinci td { background: #f8fafc; font-size: 9.5px; }
        ul.tight { margin: 0; padding-left: 14px; } ul.tight li { margin: 0; }
        .ttd { height: 46px; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; margin: 0; max-width: none; padding: 0; }
            @page { size: A4 landscape; margin: 12mm; @bottom-right { content: "Formulir RPS OBE{{ $univ ? ' – ' . $univ : '' }} | Halaman " counter(page); font-size: 8px; color: #666; } }
            tr { break-inside: avoid; }
            thead { display: table-header-group; }
            h2 { break-after: avoid; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <a href="javascript:history.back()" class="btn ghost">← Kembali</a>
    <button class="btn" onclick="window.print()">Cetak / Simpan PDF</button>
</div>

<div class="sheet">
    <table class="kop">
        <tr>
            <td class="logo" rowspan="2">@if($logo)<img src="{{ $logo }}" alt="Logo" style="max-width:80px; max-height:80px;">@else LOGO<br>INSTITUSI @endif</td>
            <td class="inst" rowspan="2">
                <div class="u">{{ $univ ? mb_strtoupper($univ) : 'INSTITUSI' }}</div>
                @if($kop['fakultas'])<div>{{ $kop['fakultas'] }}</div>@endif
                @if($kop['prodi'])<div>Program Studi {{ $kop['prodi'] }}</div>@endif
            </td>
            <td class="form" rowspan="2">
                FORMULIR MUTU<br>RENCANA PEMBELAJARAN SEMESTER<br>
                <span class="xs">SISTEM PENJAMINAN MUTU INTERNAL{{ $univ ? ' – ' . $univ : '' }}</span>
            </td>
            <td class="meta">
                <div>Kode/No: <b>{{ $d($kop['kode_dokumen']) }}</b></div>
                <div>Tanggal: <b>{{ $d($kop['tanggal']) }}</b></div>
                <div>Revisi: <b>{{ $kop['revisi'] }}</b></div>
            </td>
        </tr>
        <tr>
            <td class="meta">
                <div>Berlaku mulai: <b>{{ $d($kop['berlaku_mulai']) }}</b></div>
                <div>Tahun Akademik: <b>{{ $d($kop['tahun_akademik']) }}</b></div>
                <div>Status: <b>{{ $kop['status'] }}</b></div>
            </td>
        </tr>
    </table>

    <div class="judul">
        <div class="t1">RENCANA PEMBELAJARAN SEMESTER (RPS)</div>
        <div class="t2">KURIKULUM BERBASIS OUTCOME BASED EDUCATION (OBE)</div>
    </div>
    <div class="prinsip">Prinsip pengisian: RPS memuat perencanaan pembelajaran dan asesmen. Evaluasi hasil ketercapaian CPMK/CPL semester dilakukan menggunakan Form Evaluasi OBE Program Studi yang berlaku umum untuk seluruh mata kuliah.</div>

    <h2>A. IDENTITAS MATA KULIAH DAN OTORISASI</h2>
    <table>
        <tr><td class="lbl">Mata Kuliah</td><td>{{ $id['nama_mk'] }}</td><td class="lbl">Kode</td><td>{{ $id['kode_mk'] }}</td></tr>
        <tr><td class="lbl">Program Studi</td><td>{{ $d($id['prodi']) }}</td><td class="lbl">Jenjang</td><td>{{ $d($id['jenjang']) }}</td></tr>
        <tr><td class="lbl">Kelompok/Bidang Keahlian</td><td>{{ $d($id['bidang_keahlian']) }}</td><td class="lbl">Semester</td><td>{{ $d($id['semester']) }}</td></tr>
        <tr><td class="lbl">SKS Teori</td><td>{{ $id['sks_teori'] }}</td><td class="lbl">SKS Praktik</td><td>{{ $id['sks_praktik'] }}</td></tr>
        <tr><td class="lbl">Bentuk Pembelajaran</td><td>{{ $d($id['bentuk_pembelajaran']) }}</td><td class="lbl">Status</td><td>{{ $d($id['status_mk']) }}</td></tr>
        <tr><td class="lbl">Prasyarat</td><td>{{ $id['prasyarat'] ?? '-' }}</td><td class="lbl">Tahun Kurikulum</td><td>{{ $d($id['tahun_kurikulum']) }}</td></tr>
        <tr><td class="lbl">Tahun Akademik</td><td>{{ $d($id['tahun_akademik']) }}</td><td class="lbl">Tim Dosen</td><td>{{ $id['tim_dosen'] ? implode(', ', $id['tim_dosen']) : '—' }}</td></tr>
        @php $koor = $obe['otorisasi'][0]; @endphp
        <tr><td class="lbl">Koordinator MK</td><td>{{ $d(trim(($koor['nama'] ?? '') . ($koor['nidn'] ? ' / ' . $koor['nidn'] : ''))) }}</td><td class="lbl">Kode/No. Dokumen</td><td>{{ $d($id['kode_dokumen']) }}</td></tr>
        <tr><td class="lbl">Tanggal Penyusunan</td><td>{{ $d($id['tanggal_penyusunan']) }}</td><td class="lbl">Revisi</td><td>{{ $id['revisi'] }}</td></tr>
    </table>
    <table>
        <thead><tr><th style="width:28%">Otorisasi</th><th>Nama/NIDN/NIP</th><th style="width:22%">Tanda Tangan</th><th style="width:14%">Tanggal</th></tr></thead>
        @foreach($obe['otorisasi'] as $o)
            <tr>
                <td class="lbl">{{ $o['jabatan'] }}{{ $o['jabatan'] === 'Koordinator Bidang Keahlian' ? ' (jika ada)' : '' }}</td>
                <td>{{ $o['nama'] ?? '' }}@if($o['nidn'])<div class="muted">{{ $o['nidn'] }}</div>@endif</td>
                <td class="ttd c">
                    @if($o['ttd'] ?? null)
                        @php $img = $dataUri($o['ttd']['file'] ?? null); @endphp
                        @if($img)<img src="{{ $img }}" alt="Tanda tangan" style="max-height:42px; max-width:160px;">@endif
                        <div class="xs muted">Ditandatangani elektronik {{ $o['ttd']['waktu'] }}<br>Kode verifikasi: {{ $o['ttd']['kode'] }}</div>
                    @endif
                </td>
                <td class="c">{{ $o['tanggal'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>

    <h2>B. DESKRIPSI MATA KULIAH</h2>
    <table><tr><td class="lbl">Deskripsi</td><td class="pre">{{ $d($obe['deskripsi']) }}</td></tr></table>

    <h2>C. CPL YANG DIBEBANKAN PADA MATA KULIAH</h2>
    <table>
        <thead><tr><th style="width:12%">Kode CPL</th><th>Rumusan CPL</th><th style="width:16%">Kontribusi Mata Kuliah</th></tr></thead>
        @forelse($obe['cpl'] as $c)
            <tr><td class="b">{{ $c['kode'] }}</td><td>{{ $c['deskripsi'] }}</td><td class="c">{{ $c['kontribusi'] }}</td></tr>
        @empty
            <tr><td colspan="3" class="c muted">Belum ada CPL tertaut pada CPMK RPS ini.</td></tr>
        @endforelse
    </table>

    <h2>D. CPMK (CAPAIAN PEMBELAJARAN MATA KULIAH)</h2>
    <table>
        <thead><tr><th style="width:12%">Kode</th><th>Rumusan CPMK</th><th style="width:16%">CPL yang Didukung</th></tr></thead>
        @forelse($obe['cpmk'] as $c)
            <tr><td class="b">{{ $c['kode'] }}</td><td>{{ $c['deskripsi'] }}</td><td class="c">{{ $c['cpl'] ? implode(', ', $c['cpl']) : '—' }}</td></tr>
        @empty
            <tr><td colspan="3" class="c muted">Belum ada CPMK.</td></tr>
        @endforelse
    </table>

    <h2>E. MATRIKS CPL – CPMK (ALIGNMENT OBE)</h2>
    @php $mx = $obe['matriks_cpl_cpmk']; @endphp
    <table>
        <thead><tr><th style="width:14%">CPMK</th>@foreach($mx['cpl'] as $k)<th>{{ $k }}</th>@endforeach</tr></thead>
        @forelse($mx['baris'] as $b)
            <tr><td class="b">{{ $b['cpmk'] }}</td>@foreach($mx['cpl'] as $k)<td class="c">{{ $cek($b['cek'][$k] ?? false) }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ count($mx['cpl']) + 1 }}" class="c muted">—</td></tr>
        @endforelse
    </table>

    <h2>F. BAHAN KAJIAN</h2>
    <table>
        <thead><tr><th style="width:6%">No.</th><th>Bahan Kajian</th><th style="width:22%">CPMK yang Didukung</th></tr></thead>
        @forelse($obe['bahan_kajian'] as $i => $bk)
            <tr><td class="c">{{ $i + 1 }}</td><td>{{ $bk['nama'] }}</td><td class="c">{{ $bk['cpmk'] ? implode(', ', $bk['cpmk']) : '—' }}</td></tr>
        @empty
            <tr><td colspan="3" class="c muted">Belum ada bahan kajian.</td></tr>
        @endforelse
    </table>

    <h2>G. SUB-CPMK DAN PEMETAAN CPMK → SUB-CPMK</h2>
    <table>
        <thead><tr><th style="width:14%">Kode Sub-CPMK</th><th>Rumusan Sub-CPMK</th><th style="width:14%">CPMK Induk</th></tr></thead>
        @forelse($obe['sub_cpmk'] as $s)
            <tr><td class="b">{{ $s['kode'] }}</td><td>{{ $s['deskripsi'] }}</td><td class="c">{{ $d($s['cpmk']) }}</td></tr>
        @empty
            <tr><td colspan="3" class="c muted">Belum ada Sub-CPMK.</td></tr>
        @endforelse
    </table>
    <table>
        <thead><tr><th style="width:14%">CPMK</th><th>Sub-CPMK yang Mendukung</th></tr></thead>
        @foreach($obe['cpmk_sub'] as $cs)
            <tr><td class="b">{{ $cs['cpmk'] }}</td><td>{{ $cs['sub'] ? implode(', ', $cs['sub']) : '—' }}</td></tr>
        @endforeach
    </table>

    <h2>H. RENCANA PEMBELAJARAN MINGGUAN</h2>
    <table>
        <thead>
            <tr>
                <th style="width:3%">Mg</th>
                <th style="width:8%">Sub-CPMK</th>
                <th style="width:13%">Indikator &amp; Kriteria Ketercapaian</th>
                <th style="width:11%">Bahan Kajian</th>
                <th style="width:11%">Bentuk &amp; Metode Pembelajaran</th>
                <th style="width:11%">Pengalaman Belajar Mahasiswa</th>
                <th style="width:9%">Media/Sumber</th>
                <th style="width:10%">Asesmen &amp; Instrumen</th>
                <th style="width:9%">Bukti/Produk</th>
                <th style="width:5%">Bobot (%)</th>
                <th style="width:10%">Waktu</th>
            </tr>
        </thead>
        <tbody>
        @forelse($obe['mingguan'] as $m)
            <tr class="{{ $m['ujian'] ? 'ujian' : '' }}">
                <td class="c b">{{ $m['minggu_ke'] }}</td>
                <td>
                    @if($m['ujian'])<div class="b">{{ $m['ujian'] }}</div>@endif
                    {{ $m['sub_cpmk'] ? implode(', ', $m['sub_cpmk']) : ($m['ujian'] ? 'CPMK/Sub-CPMK yang diukur' : '—') }}
                </td>
                <td>
                    <div class="pre">{{ $d($m['indikator']) }}</div>
                    @if($m['kriteria'])<div class="xs muted" style="margin-top:2px;">Kriteria: {{ $m['kriteria'] }}</div>@endif
                </td>
                <td>{{ $d($m['bahan_kajian']) }}</td>
                <td>@forelse($m['bentuk_metode'] as $bm)<div>{{ $bm }}</div>@empty — @endforelse</td>
                <td>{{ $d($m['pengalaman_belajar']) }}</td>
                <td>{{ $d($m['media_sumber']) }}</td>
                <td>@forelse($m['asesmen'] as $a)<div>{{ $a }}</div>@empty — @endforelse</td>
                <td>{{ $d($m['bukti_produk']) }}</td>
                <td class="c">{{ $angka($m['bobot']) }}</td>
                <td class="xs">{{ $d($m['waktu']) }}</td>
            </tr>
            @if($m['rincian_pertemuan'])
                <tr class="rinci">
                    <td></td>
                    <td colspan="10">
                        <span class="b">Rincian Pertemuan:</span>
                        @foreach($m['rincian_pertemuan'] as $p)
                            @php $skenario = ! empty($p['tahapan']) && is_array($p['tahapan']); @endphp
                            <div style="margin-top:2px;">
                                <span class="b">{{ $skenario ? 'Skenario Pertemuan' : 'Pertemuan ' . ($p['pertemuan_ke'] ?? '?') }}@if(! empty($p['durasi_menit'])) · {{ (int) $p['durasi_menit'] }} menit @endif:</span> {{ $p['topik'] ?? '—' }}
                                @if(! empty($p['metode']))<div class="muted">Metode: {{ $p['metode'] }}</div>@endif
                                @if(! empty($p['aktivitas']))<div class="muted">Aktivitas/Penugasan: {{ $p['aktivitas'] }}</div>@endif
                                @if($skenario)
                                    @foreach($p['tahapan'] as $t)
                                        <div style="margin-left:8px;"><span class="b">{{ $t['tahap'] ?? 'Tahap' }}@if(! empty($t['durasi_menit'])) ({{ (int) $t['durasi_menit'] }}’)@endif:</span> {{ $t['kegiatan'] ?? '—' }}</div>
                                    @endforeach
                                    @if(! empty($p['penugasan_terstruktur']))<div class="muted" style="margin-left:8px;"><span class="b">Penugasan Terstruktur:</span> {{ $p['penugasan_terstruktur'] }}</div>@endif
                                    @if(! empty($p['belajar_mandiri']))<div class="muted" style="margin-left:8px;"><span class="b">Belajar Mandiri:</span> {{ $p['belajar_mandiri'] }}</div>@endif
                                @endif
                            </div>
                        @endforeach
                    </td>
                </tr>
            @endif
        @empty
            <tr><td colspan="11" class="c muted">Belum ada rencana mingguan.</td></tr>
        @endforelse
        </tbody>
    </table>

    <h2>I. SISTEM ASESMEN DAN BOBOT PENILAIAN</h2>
    <table>
        <thead><tr><th style="width:5%">No.</th><th>Komponen Asesmen</th><th>Teknik</th><th>Instrumen</th><th style="width:16%">CPMK yang Diukur</th><th style="width:9%">Bobot (%)</th></tr></thead>
        @forelse($obe['asesmen'] as $i => $a)
            <tr><td class="c">{{ $i + 1 }}</td><td>{{ $a['komponen'] }}</td><td>{{ $a['teknik'] }}</td><td>{{ $d($a['instrumen']) }}</td><td class="c">{{ $a['cpmk'] ? implode(', ', $a['cpmk']) : '—' }}</td><td class="c">{{ $angka($a['bobot']) }}</td></tr>
        @empty
            <tr><td colspan="6" class="c muted">Belum ada komponen penilaian.</td></tr>
        @endforelse
        <tr><td colspan="5" class="lbl r">TOTAL</td><td class="c b">{{ $angka($obe['total_bobot']) }}</td></tr>
    </table>

    <h2>J. MATRIKS ASESMEN → CPMK</h2>
    @php $ma = $obe['matriks_asesmen']; @endphp
    <table>
        <thead><tr><th style="width:24%">Komponen Asesmen</th>@foreach($ma['cpmk'] as $k)<th>{{ $k }}</th>@endforeach</tr></thead>
        @forelse($ma['baris'] as $b)
            <tr><td>{{ $b['komponen'] }}</td>@foreach($ma['cpmk'] as $k)<td class="c">{{ $cek($b['cek'][$k] ?? false) }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ count($ma['cpmk']) + 1 }}" class="c muted">—</td></tr>
        @endforelse
    </table>

    <h2>K. KRITERIA PENILAIAN DAN RUBRIK</h2>
    <table>
        <thead><tr><th style="width:20%">Asesmen</th><th>Aspek/Kriteria Utama</th><th style="width:12%">Skala/Range</th><th style="width:20%">Instrumen</th></tr></thead>
        @forelse($obe['kriteria'] as $k)
            <tr><td>{{ $k['asesmen'] }}</td><td>{{ $d($k['aspek']) }}</td><td class="c">{{ $k['skala'] }}</td><td>{{ $d($k['instrumen']) }}</td></tr>
        @empty
            <tr><td colspan="4" class="c muted">—</td></tr>
        @endforelse
    </table>
    <ul class="tight xs">
        <li>Metode asesmen dipilih sesuai karakteristik CPMK.</li>
        <li>Untuk praktikum, gunakan bukti kinerja nyata, logbook, produk/hasil pengamatan, laporan, checklist/rubrik, dan/atau OSPE/OSCE sesuai relevansi.</li>
        <li>Gunakan formative assessment untuk umpan balik dan summative assessment untuk penentuan capaian akhir.</li>
        <li>Authentic, performance, dan reflective assessment digunakan bila relevan dengan karakteristik mata kuliah.</li>
    </ul>

    <h2>L. MEDIA, LMS, PENGALAMAN BELAJAR, DAN SUMBER PEMBELAJARAN</h2>
    @php $md = $obe['media']; @endphp
    <table>
        <tr><td class="lbl">Media Pembelajaran</td><td>{{ $d($md['media_pembelajaran']) }}</td></tr>
        <tr><td class="lbl">LMS</td><td>{{ $d($md['lms']) }}</td></tr>
        <tr><td class="lbl">Pengalaman Belajar Utama</td><td>{{ $d($md['pengalaman_belajar_utama']) }}</td></tr>
        <tr><td class="lbl">Proporsi Luring/Daring</td><td>{{ $md['proporsi_luring'] !== null ? $md['proporsi_luring'] . '% luring; ' . $md['proporsi_daring'] . '% daring' : '—' }}</td></tr>
        <tr><td class="lbl">Sumber Belajar Utama</td><td>{{ $d($md['sumber_belajar_utama']) }}</td></tr>
    </table>

    <h2>M. KRITERIA KELULUSAN DAN KONVERSI NILAI</h2>
    <table style="width:60%;">
        <thead><tr><th>Rentang Nilai Angka</th><th>Nilai Huruf</th><th>Keterangan</th></tr></thead>
        @foreach($obe['skala_nilai'] as $s)
            <tr><td class="c">{{ $s['rentang'] }}</td><td class="c b">{{ $s['huruf'] }}</td><td>{{ $d($s['keterangan']) }}</td></tr>
        @endforeach
    </table>

    <h2>N. EVALUASI KETERCAPAIAN PEMBELAJARAN</h2>
    <table>
        <thead><tr><th style="width:22%">Komponen</th><th>Bukti/Instrumen</th><th style="width:18%">CPMK yang Diukur</th><th style="width:20%">Digunakan untuk Evaluasi</th></tr></thead>
        @forelse($obe['evaluasi'] as $e)
            <tr><td>{{ $e['komponen'] }}</td><td>{{ $d($e['bukti']) }}</td><td class="c">{{ $e['cpmk'] ? implode(', ', $e['cpmk']) : '—' }}</td><td>{{ $e['digunakan'] }}</td></tr>
        @empty
            <tr><td colspan="4" class="c muted">—</td></tr>
        @endforelse
    </table>
    <p class="xs muted"><i>Catatan: RPS tidak memuat tabel hasil ketercapaian mahasiswa. Hasil aktual semester dicatat pada Form Evaluasi OBE Program Studi.</i></p>

    <h2>O. DAFTAR REFERENSI</h2>
    <table>
        <thead><tr><th style="width:24%">Jenis</th><th>Referensi</th></tr></thead>
        @forelse($obe['referensi'] as $r)
            <tr><td class="lbl">{{ $r['jenis'] }}</td><td>{{ $r['sitasi'] }}</td></tr>
        @empty
            <tr><td colspan="2" class="c muted">Belum ada referensi.</td></tr>
        @endforelse
    </table>

    <h2>P. LAMPIRAN INSTRUMEN PEMBELAJARAN DAN ASESMEN</h2>
    <table>
        <thead><tr><th style="width:5%">No.</th><th>Lampiran</th><th style="width:26%">Wajib/Jika Relevan</th><th style="width:9%">Status</th></tr></thead>
        @foreach($obe['lampiran'] as $i => $l)
            <tr><td class="c">{{ $i + 1 }}</td><td>{{ $l['label'] }}</td><td>{{ $l['ketentuan'] }}</td><td class="c">{{ $l['ada'] ? '☑' : '☐' }}</td></tr>
        @endforeach
    </table>

    <p class="muted xs" style="margin-top:20px; text-align:right;">Formulir RPS OBE{{ $univ ? ' – ' . $univ : '' }} · Versi {{ $rps->versi }} · Dicetak {{ now()->format('d M Y H:i') }}</p>
</div>
</body>
</html>
