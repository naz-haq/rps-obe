<?php

namespace App\Services\Rps;

use App\Models\RpsVersion;
use App\Services\Media\GambarService;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\SimpleType\TblWidth;

/**
 * Ekspor RPS format OBE (formulir mutu, bagian A–P) ke Word (.docx).
 * Isi mengikuti resources/views/rps/cetak-obe.blade.php.
 */
class RpsObeDocxExporter
{
    private const BRAND = '00A651';
    private const SOFT = 'F3F8EE';
    private const SECT = 'E2F0D9';
    private const WARN = 'FFF2CC';
    private const LINE = '9CA3AF';
    private const INK = '222222';
    private const MUTED = '666666';
    /** Lebar isi halaman landscape (Letter, margin 936 twip). */
    private const W = 14000;

    public function __construct(private RpsObeContext $context, private GambarService $gambar) {}

    public function build(RpsVersion $rps): PhpWord
    {
        $o = $this->context->build($rps, true);
        $univ = $o['kop']['universitas'] ?? null;

        Settings::setOutputEscapingEnabled(true);
        $w = new PhpWord();
        $w->setDefaultFontName('Calibri');
        $w->setDefaultFontSize(8);

        $s = $w->addSection([
            'orientation' => 'landscape',
            'pageSizeW'   => 15840,
            'pageSizeH'   => 12240,
            'marginTop'   => 936,
            'marginBottom' => 936,
            'marginLeft' => 936,
            'marginRight' => 936,
        ]);
        $s->addFooter()->addPreserveText(
            'Formulir RPS OBE' . ($univ ? ' – ' . $univ : '') . ' | Halaman {PAGE}',
            ['size' => 7, 'color' => self::MUTED],
            ['alignment' => Jc::END]
        );

        $this->kop($s, $o['kop']);
        $s->addText('RENCANA PEMBELAJARAN SEMESTER (RPS)', ['bold' => true, 'size' => 13], ['alignment' => Jc::CENTER, 'spaceBefore' => 120, 'spaceAfter' => 0]);
        $s->addText('KURIKULUM BERBASIS OUTCOME BASED EDUCATION (OBE)', ['bold' => true, 'size' => 10, 'color' => '0B5D2E'], ['alignment' => Jc::CENTER, 'spaceAfter' => 80]);
        $t = $this->table($s);
        $t->addRow();
        $t->addCell(self::W, ['bgColor' => self::SOFT])->addText(
            'Prinsip pengisian: RPS memuat perencanaan pembelajaran dan asesmen. Evaluasi hasil ketercapaian CPMK/CPL semester dilakukan menggunakan Form Evaluasi OBE Program Studi yang berlaku umum untuk seluruh mata kuliah.',
            ['italic' => true, 'size' => 8]
        );

        $this->identitas($s, $o);
        $this->heading($s, 'B. DESKRIPSI MATA KULIAH');
        $t = $this->table($s);
        $t->addRow();
        $this->label($t, 'Deskripsi', 2600);
        $this->cell($t, $o['deskripsi'] ?? '—', self::W - 2600);

        $this->heading($s, 'C. CPL YANG DIBEBANKAN PADA MATA KULIAH');
        $this->grid(
            $s,
            ['Kode CPL', 'Rumusan CPL', 'Kontribusi Mata Kuliah'],
            [1800, 9800, 2400],
            array_map(fn($c) => [$c['kode'], $c['deskripsi'], $c['kontribusi']], $o['cpl']),
            'Belum ada CPL tertaut pada CPMK RPS ini.',
            [0 => true],
            [2 => Jc::CENTER]
        );

        $this->heading($s, 'D. CPMK (CAPAIAN PEMBELAJARAN MATA KULIAH)');
        $this->grid(
            $s,
            ['Kode', 'Rumusan CPMK', 'CPL yang Didukung'],
            [1800, 9800, 2400],
            array_map(fn($c) => [$c['kode'], $c['deskripsi'], $this->list($c['cpl'])], $o['cpmk']),
            'Belum ada CPMK.',
            [0 => true],
            [2 => Jc::CENTER]
        );

        $this->heading($s, 'E. MATRIKS CPL – CPMK (ALIGNMENT OBE)');
        $this->matriks($s, 'CPMK', $o['matriks_cpl_cpmk']['cpl'], array_map(fn($b) => [$b['cpmk'], $b['cek']], $o['matriks_cpl_cpmk']['baris']));

        $this->heading($s, 'F. BAHAN KAJIAN');
        $this->grid(
            $s,
            ['No.', 'Bahan Kajian', 'CPMK yang Didukung'],
            [800, 10400, 2800],
            array_map(fn($b, $i) => [(string) ($i + 1), $b['nama'], $this->list($b['cpmk'])], $o['bahan_kajian'], array_keys($o['bahan_kajian'])),
            'Belum ada bahan kajian.',
            [],
            [0 => Jc::CENTER, 2 => Jc::CENTER]
        );

        $this->heading($s, 'G. SUB-CPMK DAN PEMETAAN CPMK → SUB-CPMK');
        $this->grid(
            $s,
            ['Kode Sub-CPMK', 'Rumusan Sub-CPMK', 'CPMK Induk'],
            [2000, 10000, 2000],
            array_map(fn($x) => [$x['kode'], $x['deskripsi'], $x['cpmk'] ?? '—'], $o['sub_cpmk']),
            'Belum ada Sub-CPMK.',
            [0 => true],
            [2 => Jc::CENTER]
        );
        $this->grid(
            $s,
            ['CPMK', 'Sub-CPMK yang Mendukung'],
            [2000, 12000],
            array_map(fn($x) => [$x['cpmk'], $this->list($x['sub'])], $o['cpmk_sub']),
            null,
            [0 => true]
        );

        $this->mingguan($s, $o['mingguan']);

        $this->heading($s, 'I. SISTEM ASESMEN DAN BOBOT PENILAIAN');
        $widths = [700, 3000, 2600, 3900, 2400, 1400];
        $t = $this->grid(
            $s,
            ['No.', 'Komponen Asesmen', 'Teknik', 'Instrumen', 'CPMK yang Diukur', 'Bobot (%)'],
            $widths,
            array_map(fn($a, $i) => [(string) ($i + 1), $a['komponen'], $a['teknik'], $a['instrumen'] ?? '—', $this->list($a['cpmk']), $this->angka($a['bobot'])], $o['asesmen'], array_keys($o['asesmen'])),
            'Belum ada komponen penilaian.',
            [],
            [0 => Jc::CENTER, 4 => Jc::CENTER, 5 => Jc::CENTER]
        );
        $t->addRow();
        $t->addCell(array_sum($widths) - 1400, ['gridSpan' => 5, 'bgColor' => self::SOFT])->addText('TOTAL', ['bold' => true], ['alignment' => Jc::END]);
        $this->cell($t, $this->angka($o['total_bobot']), 1400, Jc::CENTER, true);

        $this->heading($s, 'J. MATRIKS ASESMEN → CPMK');
        $this->matriks($s, 'Komponen Asesmen', $o['matriks_asesmen']['cpmk'], array_map(fn($b) => [$b['komponen'], $b['cek']], $o['matriks_asesmen']['baris']));

        $this->heading($s, 'K. KRITERIA PENILAIAN DAN RUBRIK');
        $this->grid(
            $s,
            ['Asesmen', 'Aspek/Kriteria Utama', 'Skala/Range', 'Instrumen'],
            [2800, 6600, 1600, 3000],
            array_map(fn($k) => [$k['asesmen'], $k['aspek'] ?? '—', $k['skala'], $k['instrumen'] ?? '—'], $o['kriteria']),
            '—',
            [],
            [2 => Jc::CENTER]
        );
        foreach (
            [
                'Metode asesmen dipilih sesuai karakteristik CPMK.',
                'Untuk praktikum, gunakan bukti kinerja nyata, logbook, produk/hasil pengamatan, laporan, checklist/rubrik, dan/atau OSPE/OSCE sesuai relevansi.',
                'Gunakan formative assessment untuk umpan balik dan summative assessment untuk penentuan capaian akhir.',
                'Authentic, performance, dan reflective assessment digunakan bila relevan dengan karakteristik mata kuliah.',
            ] as $butir
        ) {
            $s->addText('• ' . $butir, ['size' => 7.5], ['spaceAfter' => 0]);
        }

        $this->heading($s, 'L. MEDIA, LMS, PENGALAMAN BELAJAR, DAN SUMBER PEMBELAJARAN');
        $md = $o['media'];
        $this->grid($s, ['Komponen', 'Isian'], [3200, 10800], [
            ['Media Pembelajaran', $md['media_pembelajaran'] ?? '—'],
            ['LMS', $md['lms'] ?? '—'],
            ['Pengalaman Belajar Utama', $md['pengalaman_belajar_utama'] ?? '—'],
            ['Proporsi Luring/Daring', $md['proporsi_luring'] !== null ? $md['proporsi_luring'] . '% luring; ' . $md['proporsi_daring'] . '% daring' : '—'],
            ['Sumber Belajar Utama', $md['sumber_belajar_utama'] ?? '—'],
        ], null, [0 => true]);

        $this->heading($s, 'M. KRITERIA KELULUSAN DAN KONVERSI NILAI');
        $this->grid(
            $s,
            ['Rentang Nilai Angka', 'Nilai Huruf', 'Keterangan'],
            [3000, 2000, 4000],
            array_map(fn($x) => [$x['rentang'], $x['huruf'], $x['keterangan'] ?? '—'], $o['skala_nilai']),
            null,
            [1 => true],
            [0 => Jc::CENTER, 1 => Jc::CENTER]
        );

        $this->heading($s, 'N. EVALUASI KETERCAPAIAN PEMBELAJARAN');
        $this->grid(
            $s,
            ['Komponen', 'Bukti/Instrumen', 'CPMK yang Diukur', 'Digunakan untuk Evaluasi'],
            [3000, 5400, 2800, 2800],
            array_map(fn($e) => [$e['komponen'], $e['bukti'] ?: '—', $this->list($e['cpmk']), $e['digunakan']], $o['evaluasi']),
            '—',
            [],
            [2 => Jc::CENTER]
        );
        $s->addText('Catatan: RPS tidak memuat tabel hasil ketercapaian mahasiswa. Hasil aktual semester dicatat pada Form Evaluasi OBE Program Studi.', ['italic' => true, 'size' => 7.5, 'color' => self::MUTED]);

        $this->heading($s, 'O. DAFTAR REFERENSI');
        $this->grid(
            $s,
            ['Jenis', 'Referensi'],
            [3400, 10600],
            array_map(fn($r) => [$r['jenis'], $r['sitasi']], $o['referensi']),
            'Belum ada referensi.',
            [0 => true]
        );

        $this->heading($s, 'P. LAMPIRAN INSTRUMEN PEMBELAJARAN DAN ASESMEN');
        $this->grid(
            $s,
            ['No.', 'Lampiran', 'Wajib/Jika Relevan', 'Status'],
            [700, 7800, 4000, 1500],
            array_map(fn($l, $i) => [(string) ($i + 1), $l['label'], $l['ketentuan'], $l['ada'] ? '☑' : '☐'], $o['lampiran'], array_keys($o['lampiran'])),
            null,
            [],
            [0 => Jc::CENTER, 3 => Jc::CENTER]
        );

        return $w;
    }

    private function kop(Section $s, array $kop): void
    {
        $univ = $kop['universitas'] ?? null;
        $t = $this->table($s);
        $meta = fn(array $pairs) => function ($cell) use ($pairs) {
            foreach ($pairs as [$k, $v]) {
                $r = $cell->addTextRun(['spaceAfter' => 0]);
                $r->addText($k . ': ', ['size' => 8]);
                $r->addText((string) ($v ?? '—'), ['bold' => true, 'size' => 8]);
            }
        };

        $t->addRow();
        $logo = $t->addCell(1600, ['vMerge' => 'restart', 'valign' => 'center']);
        if ($file = $this->gambar->untukDocx($kop['logo_file'] ?? null)) {
            $logo->addImage($file, ['height' => 48, 'alignment' => Jc::CENTER]);
        } else {
            $logo->addText('LOGO', ['bold' => true, 'color' => self::MUTED], ['alignment' => Jc::CENTER]);
        }
        $c = $t->addCell(5400, ['vMerge' => 'restart', 'valign' => 'center']);
        $c->addText(mb_strtoupper($univ ?? 'INSTITUSI'), ['bold' => true, 'size' => 11], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        if ($kop['fakultas']) {
            $c->addText((string) $kop['fakultas'], ['size' => 9], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        }
        if ($kop['prodi']) {
            $c->addText('Program Studi ' . $kop['prodi'], ['size' => 9], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        }
        $c = $t->addCell(4200, ['vMerge' => 'restart', 'valign' => 'center']);
        foreach (['FORMULIR MUTU', 'RENCANA PEMBELAJARAN SEMESTER'] as $baris) {
            $c->addText($baris, ['bold' => true, 'size' => 9], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        }
        $c->addText('SISTEM PENJAMINAN MUTU INTERNAL' . ($univ ? ' – ' . $univ : ''), ['size' => 7.5], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        $meta([['Kode/No', $kop['kode_dokumen']], ['Tanggal', $kop['tanggal']], ['Revisi', $kop['revisi']]])($t->addCell(2800));

        $t->addRow();
        $t->addCell(1600, ['vMerge' => 'continue']);
        $t->addCell(5400, ['vMerge' => 'continue']);
        $t->addCell(4200, ['vMerge' => 'continue']);
        $meta([['Berlaku mulai', $kop['berlaku_mulai']], ['Tahun Akademik', $kop['tahun_akademik']], ['Status', $kop['status']]])($t->addCell(2800));
    }

    private function identitas(Section $s, array $o): void
    {
        $id = $o['identitas'];
        $koor = $o['otorisasi'][0];
        $this->heading($s, 'A. IDENTITAS MATA KULIAH DAN OTORISASI');
        $t = $this->table($s);
        $v = fn($x) => ($x === null || $x === '' || $x === []) ? '—' : (string) $x;
        $pasangan = [
            ['Mata Kuliah', $id['nama_mk'], 'Kode', $id['kode_mk']],
            ['Program Studi', $id['prodi'], 'Jenjang', $id['jenjang']],
            ['Kelompok/Bidang Keahlian', $id['bidang_keahlian'], 'Semester', $id['semester']],
            ['SKS Teori', $id['sks_teori'], 'SKS Praktik', $id['sks_praktik']],
            ['Bentuk Pembelajaran', $id['bentuk_pembelajaran'], 'Status', $id['status_mk']],
            ['Prasyarat', $id['prasyarat'] ?? '-', 'Tahun Kurikulum', $id['tahun_kurikulum']],
            ['Tahun Akademik', $id['tahun_akademik'], 'Tim Dosen', implode(', ', $id['tim_dosen'])],
            ['Koordinator MK', trim(($koor['nama'] ?? '') . ($koor['nidn'] ? ' / ' . $koor['nidn'] : '')), 'Kode/No. Dokumen', $id['kode_dokumen']],
            ['Tanggal Penyusunan', $id['tanggal_penyusunan'], 'Revisi', $id['revisi']],
        ];
        foreach ($pasangan as [$l1, $v1, $l2, $v2]) {
            $t->addRow();
            $this->label($t, $l1, 2600);
            $this->cell($t, $v($v1), 4400);
            $this->label($t, $l2, 2600);
            $this->cell($t, $v($v2), 4400);
        }

        $s->addText('', ['size' => 4], ['spaceAfter' => 0]);
        $t = $this->table($s);
        $this->head($t, ['Otorisasi', 'Nama/NIDN/NIP', 'Tanda Tangan', 'Tanggal'], [4000, 4600, 3400, 2000]);
        foreach ($o['otorisasi'] as $x) {
            $t->addRow(700);
            $this->label($t, $x['jabatan'] . ($x['jabatan'] === 'Koordinator Bidang Keahlian' ? ' (jika ada)' : ''), 4000);
            $c = $t->addCell(4600);
            $c->addText((string) ($x['nama'] ?? ''), ['size' => 8], ['spaceAfter' => 0]);
            if ($x['nidn']) {
                $c->addText((string) $x['nidn'], ['size' => 7.5, 'color' => self::MUTED]);
            }
            $ttd = $t->addCell(3400, ['valign' => 'center']);
            if (! empty($x['ttd'])) {
                if ($file = $this->gambar->untukDocx($x['ttd']['file'] ?? null)) {
                    $ttd->addImage($file, ['height' => 32, 'alignment' => Jc::CENTER]);
                }
                $ttd->addText('Ditandatangani elektronik ' . $x['ttd']['waktu'], ['size' => 6.5, 'color' => self::MUTED], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
                $ttd->addText('Kode verifikasi: ' . $x['ttd']['kode'], ['size' => 6.5, 'color' => self::MUTED], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
            }
            $t->addCell(2000)->addText((string) ($x['tanggal'] ?? ''), ['size' => 8], ['alignment' => Jc::CENTER]);
        }
    }

    private function mingguan(Section $s, array $rows): void
    {
        $this->heading($s, 'H. RENCANA PEMBELAJARAN MINGGUAN');
        $head = ['Mg', 'Sub-CPMK', 'Indikator & Kriteria Ketercapaian', 'Bahan Kajian', 'Bentuk & Metode Pembelajaran', 'Pengalaman Belajar Mahasiswa', 'Media/Sumber', 'Asesmen & Instrumen', 'Bukti/Produk', 'Bobot (%)', 'Waktu'];
        $w = [450, 1150, 1850, 1500, 1550, 1550, 1250, 1450, 1250, 650, 1350];
        $t = $this->table($s);
        $this->head($t, $head, $w);
        if ($rows === []) {
            $t->addRow();
            $t->addCell(array_sum($w), ['gridSpan' => count($w)])->addText('Belum ada rencana mingguan.', ['color' => self::MUTED], ['alignment' => Jc::CENTER]);

            return;
        }
        foreach ($rows as $m) {
            $bg = $m['ujian'] ? ['bgColor' => self::WARN] : [];
            $t->addRow(null, ['cantSplit' => true]);
            $this->cell($t, (string) $m['minggu_ke'], $w[0], Jc::CENTER, true, $bg);
            $c = $t->addCell($w[1], $bg);
            if ($m['ujian']) {
                $c->addText($m['ujian'], ['bold' => true, 'size' => 7.5], ['spaceAfter' => 0]);
            }
            $c->addText($m['sub_cpmk'] ? implode(', ', $m['sub_cpmk']) : ($m['ujian'] ? 'CPMK/Sub-CPMK yang diukur' : '—'), ['size' => 7.5]);
            $c = $t->addCell($w[2], $bg);
            foreach (explode("\n", (string) ($m['indikator'] ?: '—')) as $baris) {
                $c->addText($baris, ['size' => 7.5], ['spaceAfter' => 0]);
            }
            if ($m['kriteria']) {
                $c->addText('Kriteria: ' . $m['kriteria'], ['size' => 7, 'italic' => true, 'color' => self::MUTED]);
            }
            $this->cell($t, $m['bahan_kajian'] ?: '—', $w[3], Jc::START, false, $bg, 7.5);
            $this->lines($t, $m['bentuk_metode'], $w[4], $bg);
            $this->cell($t, $m['pengalaman_belajar'] ?: '—', $w[5], Jc::START, false, $bg, 7.5);
            $this->cell($t, $m['media_sumber'] ?: '—', $w[6], Jc::START, false, $bg, 7.5);
            $this->lines($t, $m['asesmen'], $w[7], $bg);
            $this->cell($t, $m['bukti_produk'] ?: '—', $w[8], Jc::START, false, $bg, 7.5);
            $this->cell($t, $this->angka($m['bobot']), $w[9], Jc::CENTER, false, $bg, 7.5);
            $this->cell($t, $m['waktu'] ?: '—', $w[10], Jc::START, false, $bg, 7);

            if ($m['rincian_pertemuan']) {
                $t->addRow();
                $t->addCell($w[0], ['bgColor' => 'F8FAFC']);
                $c = $t->addCell(array_sum($w) - $w[0], ['gridSpan' => count($w) - 1, 'bgColor' => 'F8FAFC']);
                $c->addText('Rincian Pertemuan:', ['bold' => true, 'size' => 7.5], ['spaceAfter' => 0]);
                foreach ($m['rincian_pertemuan'] as $p) {
                    if (! is_array($p)) {
                        continue;
                    }
                    $tahapan = is_array($p['tahapan'] ?? null) ? $p['tahapan'] : [];
                    $r = $c->addTextRun(['spaceAfter' => 0]);
                    $durasi = ! empty($p['durasi_menit']) ? ' · ' . (int) $p['durasi_menit'] . ' menit' : '';
                    $r->addText(($tahapan !== [] ? 'Skenario Pertemuan' : 'Pertemuan ' . ($p['pertemuan_ke'] ?? '?')) . $durasi . ': ', ['bold' => true, 'size' => 7.5]);
                    $r->addText(trim((string) ($p['topik'] ?? '—')), ['size' => 7.5]);
                    foreach (['metode' => 'Metode', 'aktivitas' => 'Aktivitas/Penugasan', 'penugasan_terstruktur' => 'Penugasan Terstruktur', 'belajar_mandiri' => 'Belajar Mandiri'] as $k => $lbl) {
                        if (trim((string) ($p[$k] ?? '')) !== '') {
                            $c->addText('  ' . $lbl . ': ' . trim((string) $p[$k]), ['size' => 7, 'italic' => true, 'color' => self::MUTED], ['spaceAfter' => 0]);
                        }
                    }
                    foreach ($tahapan as $tp) {
                        if (is_array($tp)) {
                            $menit = ! empty($tp['durasi_menit']) ? ' (' . (int) $tp['durasi_menit'] . '’)' : '';
                            $c->addText('  ' . (trim((string) ($tp['tahap'] ?? '')) ?: 'Tahap') . $menit . ': ' . trim((string) ($tp['kegiatan'] ?? '—')), ['size' => 7], ['spaceAfter' => 0]);
                        }
                    }
                }
            }
        }
    }

    /** @param list<array{0:string,1:array<string,bool>}> $rows [label baris, peta centang per kolom] */
    private function matriks(Section $s, string $judul, array $kolom, array $rows): void
    {
        $first = 3200;
        $colW = (int) max(700, (self::W - $first) / max(1, count($kolom)));
        $widths = array_merge([$first], array_fill(0, count($kolom), $colW));
        $this->grid(
            $s,
            array_merge([$judul], $kolom),
            $widths,
            array_map(fn($r) => array_merge([$r[0]], array_map(fn($k) => ($r[1][$k] ?? false) ? '✓' : '–', $kolom)), $rows),
            '—',
            [0 => true],
            array_fill_keys(range(1, max(1, count($kolom))), Jc::CENTER)
        );
    }

    // ---- helper ----

    private function heading(Section $s, string $teks): void
    {
        $t = $this->table($s, false);
        $t->addRow();
        $t->addCell(self::W, ['bgColor' => self::SECT, 'borderLeftSize' => 24, 'borderLeftColor' => self::BRAND])
            ->addText($teks, ['bold' => true, 'size' => 9.5, 'color' => '0B5D2E'], ['spaceBefore' => 20, 'spaceAfter' => 20]);
        $s->addText('', ['size' => 2], ['spaceAfter' => 0]);
    }

    private function table(Section $s, bool $border = true): Table
    {
        return $s->addTable([
            'borderSize'  => $border ? 4 : 0,
            'borderColor' => $border ? self::LINE : 'FFFFFF',
            'cellMargin'  => 50,
            'alignment'   => JcTable::CENTER,
            'width'       => 100 * 50,
            'unit'        => TblWidth::PERCENT,
        ]);
    }

    private function head(Table $t, array $head, array $widths): void
    {
        $t->addRow(null, ['tblHeader' => true]);
        foreach ($head as $i => $h) {
            $t->addCell($widths[$i], ['bgColor' => self::BRAND, 'valign' => 'center'])
                ->addText($h, ['bold' => true, 'size' => 7.5, 'color' => 'FFFFFF'], ['alignment' => Jc::CENTER]);
        }
    }

    /**
     * Tabel standar: header hijau + baris data.
     *
     * @param array<int,bool> $bold kolom tebal
     * @param array<int,string> $align perataan per kolom
     */
    private function grid(Section $s, array $head, array $widths, array $rows, ?string $kosong = null, array $bold = [], array $align = []): Table
    {
        $t = $this->table($s);
        $this->head($t, $head, $widths);
        if ($rows === [] && $kosong !== null) {
            $t->addRow();
            $t->addCell(array_sum($widths), ['gridSpan' => count($widths)])
                ->addText($kosong, ['color' => self::MUTED], ['alignment' => Jc::CENTER]);
        }
        foreach ($rows as $row) {
            $t->addRow(null, ['cantSplit' => true]);
            foreach (array_values($row) as $i => $val) {
                $this->cell($t, (string) $val, $widths[$i], $align[$i] ?? Jc::START, $bold[$i] ?? false);
            }
        }

        return $t;
    }

    private function label(Table $t, string $text, int $w): void
    {
        $t->addCell($w, ['bgColor' => self::SOFT])->addText($text, ['bold' => true, 'size' => 8]);
    }

    private function cell(Table $t, string $text, int $w, string $align = Jc::START, bool $bold = false, array $style = [], float $size = 8): void
    {
        $c = $t->addCell($w, $style);
        foreach (explode("\n", $text !== '' ? $text : '—') as $baris) {
            $c->addText($baris, ['size' => $size, 'bold' => $bold, 'color' => self::INK], ['alignment' => $align, 'spaceAfter' => 0]);
        }
    }

    private function lines(Table $t, array $items, int $w, array $style = []): void
    {
        $c = $t->addCell($w, $style);
        foreach ($items !== [] ? $items : ['—'] as $it) {
            $c->addText((string) $it, ['size' => 7.5], ['spaceAfter' => 0]);
        }
    }

    private function list(array $items): string
    {
        return $items !== [] ? implode(', ', $items) : '—';
    }

    private function angka($v): string
    {
        if ($v === null || $v === '') {
            return '—';
        }

        return rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    }
}
