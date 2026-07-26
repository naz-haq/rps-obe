<?php

namespace App\Services\Kurikulum;

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\SimpleType\TblWidth;

/**
 * Membangun Buku (Dokumen) Kurikulum dalam format Word (.docx) via PhpWord,
 * mengikuti sistematika 12 bagian (I–XII) Panduan KPT 2024 (Pasal 44
 * Permendikbudristek 53/2023).
 *
 * Bagian berbasis data (I sebagian, V–IX) diisi otomatis dari
 * BukuKurikulumBuilder. Bagian naratif akademik (Pengantar, III Landasan,
 * IX Modalitas) diisi narasi AI bila tersedia. Bagian kebijakan spesifik
 * institusi (II, IV, X, XI, XII, sebagian I) ditampilkan sebagai placeholder
 * untuk dilengkapi program studi — sistem tidak mengarang fakta institusi.
 */
class BukuKurikulumDocxExporter
{
    private const INK = '1E293B';
    private const MUTED = '64748B';
    private const BRAND = '2563EB';
    private const HEAD_FILL = 'F1F5F9';
    private const LINE = 'CBD5E1';
    private const HIT = '2563EB';

    /**
     * @param  array<string,mixed>  $buku     hasil BukuKurikulumBuilder::build()
     * @param  array<string,string>  $naratif  peta bagian => prosa (opsional)
     */
    public function build(array $buku, array $naratif = []): PhpWord
    {
        $phpWord = new PhpWord();
        \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop'    => 1100,
            'marginBottom' => 1100,
            'marginLeft'   => 1100,
            'marginRight'  => 1100,
        ]);

        $this->addSampul($section, $buku['identitas'] ?? []);
        $section->addPageBreak();

        if (! empty($naratif['pengantar'])) {
            $this->judulTanpaNomor($section, 'Kata Pengantar');
            $this->paragraf($section, (string) $naratif['pengantar']);
            $section->addPageBreak();
        }

        // I. Identitas Program Studi
        $this->bab($section, 'I', 'Identitas Program Studi');
        $this->addIdentitas($section, $buku['identitas'] ?? []);

        // II. Evaluasi Kurikulum dan Tracer Study
        $this->bab($section, 'II', 'Evaluasi Kurikulum dan Tracer Study');
        $this->naratifAtauPlaceholder(
            $section,
            $naratif['evaluasi'] ?? null,
            'Sajikan mekanisme dan hasil evaluasi kurikulum yang telah/sedang berjalan, serta analisis kebutuhan berdasarkan tracer study dan masukan pemangku kepentingan.'
        );

        // III. Landasan Perancangan dan Pengembangan Kurikulum
        $this->bab($section, 'III', 'Landasan Perancangan dan Pengembangan Kurikulum');
        $this->naratifAtauPlaceholder(
            $section,
            $naratif['landasan'] ?? null,
            'Uraikan landasan filosofis, sosiologis, psikologis, dan yuridis pengembangan kurikulum.'
        );

        // IV. Visi, Misi, Tujuan, dan Strategi
        $this->bab($section, 'IV', 'Visi, Misi, Tujuan, dan Strategi');
        $this->addVmts($section, $buku['identitas'] ?? []);

        // V. Capaian Pembelajaran Lulusan (CPL)
        $this->bab($section, 'V', 'Capaian Pembelajaran Lulusan (CPL)');
        if (! empty($naratif['cpl'])) {
            $this->paragraf($section, (string) $naratif['cpl']);
        }
        $this->subJudul($section, 'Profil Lulusan');
        $this->addProfilLulusan($section, $buku['profil_lulusan'] ?? []);
        $this->subJudul($section, 'Rumusan CPL');
        $this->addCpl($section, $buku['cpl'] ?? []);

        // VI. Penetapan Bahan Kajian
        $this->bab($section, 'VI', 'Penetapan Bahan Kajian');
        $this->addBahanKajian($section, $buku['bahan_kajian'] ?? [], $buku['matriks_cpl_bk'] ?? []);

        // VII. Pembentukan Mata Kuliah dan Penentuan Bobot SKS
        $this->bab($section, 'VII', 'Pembentukan Mata Kuliah dan Penentuan Bobot SKS');
        if (! empty($naratif['mata_kuliah'])) {
            $this->paragraf($section, (string) $naratif['mata_kuliah']);
        }
        $this->addStrukturMk($section, $buku['mata_kuliah'] ?? []);

        // VIII. Matrik, Peta Kurikulum, dan Masa Tempuh
        $this->bab($section, 'VIII', 'Matrik, Peta Kurikulum, dan Masa Tempuh');
        $this->subJudul($section, 'Matriks Profil Lulusan × CPL');
        $this->addMatriksKode($section, $buku['matriks_pl_cpl'] ?? [], 'profil', 'cpl', $this->kodeCpl($buku['cpl'] ?? []), 'Profil');
        $this->subJudul($section, 'Matriks CPL × Mata Kuliah');
        $this->addMatriksKode($section, $this->siapkanMkCpl($buku['matriks_mk_cpl'] ?? []), 'label', 'cpl', $this->kodeCpl($buku['cpl'] ?? []), 'Mata Kuliah');

        // IX. Modalitas Pembelajaran dan RPS
        $this->bab($section, 'IX', 'Modalitas Pembelajaran dan Rencana Pembelajaran Semester (RPS)');
        $this->naratifAtauPlaceholder(
            $section,
            $naratif['modalitas'] ?? null,
            'Jelaskan modalitas pembelajaran (gaya belajar, metode Student-Centered Learning, blended learning) yang menjadi dasar penyusunan RPS.'
        );
        $this->subJudul($section, 'Ringkasan RPS per Mata Kuliah');
        $this->addRpsRingkas($section, $buku['rps_ringkas'] ?? []);

        // X. Hak Belajar Maksimum 3 Semester di Luar Program Studi (MBKM)
        $this->bab($section, 'X', 'Rencana Implementasi Hak Belajar Maksimum 3 Semester di Luar Program Studi');
        $this->naratifAtauPlaceholder(
            $section,
            $naratif['mbkm'] ?? null,
            'Uraikan penempatan Bentuk Kegiatan Pembelajaran (BKP) MBKM dalam struktur kurikulum dan mekanisme pengakuan kredit.'
        );

        // XI. Manajemen dan Mekanisme Pelaksanaan Kurikulum
        $this->bab($section, 'XI', 'Manajemen dan Mekanisme Pelaksanaan Kurikulum');
        $this->naratifAtauPlaceholder(
            $section,
            $naratif['manajemen'] ?? null,
            'Jelaskan rencana pelaksanaan kurikulum dan perangkat Sistem Penjaminan Mutu Internal (SPMI).'
        );

        // XII. Tata Cara Penerimaan Mahasiswa pada Berbagai Tahapan Kurikulum
        $this->bab($section, 'XII', 'Tata Cara Penerimaan Mahasiswa pada Berbagai Tahapan Kurikulum');
        $this->naratifAtauPlaceholder(
            $section,
            $naratif['penerimaan'] ?? null,
            'Tuliskan tata cara penerimaan mahasiswa pada setiap tahapan kurikulum sesuai kebijakan dan standar perguruan tinggi.'
        );

        $section->addTextBreak(1);
        $section->addText(
            'Dokumen Kurikulum mengikuti sistematika Panduan Penyusunan Kurikulum Pendidikan Tinggi (KPT) 2024 · '
                . 'Bagian berbasis data dirakit otomatis; bagian bertanda placeholder dilengkapi program studi · '
                . now()->format('d M Y H:i'),
            ['size' => 8, 'italic' => true, 'color' => self::MUTED],
            ['alignment' => Jc::END]
        );

        return $phpWord;
    }

    // ------------------------------------------------------------------ Sampul

    private function addSampul(Section $section, array $identitas): void
    {
        $univ = strtoupper($identitas['universitas']['nama'] ?? 'INSTITUSI');
        $fak  = $identitas['fakultas']['nama'] ?? null;
        $prodi = $identitas['prodi']['nama'] ?? null;
        $kur = $identitas['kurikulum'] ?? [];

        $section->addTextBreak(3);
        $section->addText('DOKUMEN KURIKULUM', ['bold' => true, 'size' => 22, 'color' => self::BRAND], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(1);
        if ($prodi) {
            $section->addText('Program Studi ' . $prodi, ['bold' => true, 'size' => 16], ['alignment' => Jc::CENTER]);
        }
        $section->addText((string) ($kur['nama'] ?? ''), ['size' => 13], ['alignment' => Jc::CENTER]);
        if (! empty($kur['tahun'])) {
            $section->addText('Tahun ' . $kur['tahun'], ['size' => 12, 'color' => self::MUTED], ['alignment' => Jc::CENTER]);
        }
        $section->addTextBreak(6);
        if ($fak) {
            $section->addText($fak, ['bold' => true, 'size' => 13], ['alignment' => Jc::CENTER]);
        }
        $section->addText($univ, ['bold' => true, 'size' => 15], ['alignment' => Jc::CENTER]);
        if (! empty($kur['status'])) {
            $section->addTextBreak(1);
            $section->addText('Status: ' . ucfirst((string) $kur['status']), ['size' => 10, 'color' => self::MUTED], ['alignment' => Jc::CENTER]);
        }
    }

    // ------------------------------------------------------------------ Bab I

    private function addIdentitas(Section $section, array $identitas): void
    {
        $kur = $identitas['kurikulum'] ?? [];
        $prodi = $identitas['prodi'] ?? [];
        $table = $this->tabel($section);
        $baris = [
            ['Perguruan Tinggi', $identitas['universitas']['nama'] ?? null],
            ['Fakultas', $identitas['fakultas']['nama'] ?? null],
            ['Program Studi', $prodi['nama'] ?? null],
            ['Akreditasi', $prodi['akreditasi'] ?? null],
            ['Jenjang Pendidikan', $prodi['jenjang'] ?? null],
            ['Gelar Lulusan', $prodi['gelar'] ?? null],
            ['Nama Kurikulum', $kur['nama'] ?? null],
            ['Tahun Kurikulum', $kur['tahun'] ?? null],
            ['Status', isset($kur['status']) ? ucfirst((string) $kur['status']) : null],
            ['Visi', $identitas['vmts']['visi'] ?? null],
        ];
        foreach ($baris as [$label, $nilai]) {
            $table->addRow();
            $c1 = $table->addCell(3200, ['bgColor' => self::HEAD_FILL, 'valign' => 'center']);
            $c1->addText($label, ['size' => 10, 'bold' => true, 'color' => self::INK], ['spaceAfter' => 0]);
            $c2 = $table->addCell(6800, ['valign' => 'center']);
            if ($nilai !== null && $nilai !== '') {
                $c2->addText((string) $nilai, ['size' => 10, 'color' => self::INK], ['spaceAfter' => 0]);
            } else {
                $c2->addText('[Dilengkapi oleh program studi]', ['size' => 10, 'italic' => true, 'color' => self::MUTED], ['spaceAfter' => 0]);
            }
        }
    }

    /** Bab IV: VMTS versi terpilih + University Value. */
    private function addVmts(Section $section, array $identitas): void
    {
        $vmts = $identitas['vmts'] ?? null;
        $nilai = $identitas['universitas']['nilai_institusi'] ?? null;

        if (! $vmts && ! $nilai) {
            $this->naratifAtauPlaceholder($section, null, 'Pilih/isi VMTS prodi (menu Prodi → VMTS) lalu tautkan ke kurikulum ini.');
            return;
        }

        if (! empty($vmts['label'])) {
            $section->addText('Sumber: ' . $vmts['label'], ['size' => 9, 'italic' => true, 'color' => self::MUTED], ['spaceAfter' => 80]);
        }
        if (! empty($vmts['visi'])) {
            $this->subJudul($section, 'Visi');
            $this->paragraf($section, (string) $vmts['visi']);
        }
        $this->daftarBernomor($section, 'Misi', $vmts['misi'] ?? []);
        $this->daftarBernomor($section, 'Tujuan', $vmts['tujuan'] ?? []);
        $this->daftarBernomor($section, 'Strategi', $vmts['strategi'] ?? []);

        if ($nilai) {
            $this->subJudul($section, 'University Value');
            $this->paragraf($section, (string) $nilai);
        }
    }

    /** @param list<string> $items */
    private function daftarBernomor(Section $section, string $judul, array $items): void
    {
        if ($items === []) {
            return;
        }
        $this->subJudul($section, $judul);
        $i = 1;
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            $section->addText(($i++) . '. ' . $item, ['size' => 11, 'color' => self::INK], ['alignment' => Jc::BOTH, 'spaceAfter' => 60]);
        }
    }

    // ------------------------------------------------------------------ Sub-renderer data

    private function addProfilLulusan(Section $section, array $items): void
    {
        $table = $this->tabel($section);
        $this->baris($table, ['Kode', 'Deskripsi Profil Lulusan'], [1500, 8500], true);
        foreach ($items as $it) {
            $this->baris($table, [(string) ($it['kode'] ?? ''), (string) ($it['deskripsi'] ?? '')], [1500, 8500]);
        }
    }

    private function addCpl(Section $section, array $items): void
    {
        $table = $this->tabel($section);
        $this->baris($table, ['Kode', 'Aspek', 'KKNI', 'Deskripsi CPL'], [1200, 1800, 900, 6100], true);
        foreach ($items as $it) {
            $this->baris($table, [
                (string) ($it['kode'] ?? ''),
                (string) ($it['aspek'] ?? '-'),
                (string) ($it['level_kkni'] ?? '-'),
                (string) ($it['deskripsi'] ?? ''),
            ], [1200, 1800, 900, 6100]);
        }
    }

    private function addBahanKajian(Section $section, array $bk, array $matriksCplBk): void
    {
        $table = $this->tabel($section);
        $this->baris($table, ['Bahan Kajian', 'Deskripsi'], [3200, 6800], true);
        foreach ($bk as $it) {
            $this->baris($table, [(string) ($it['nama'] ?? ''), (string) ($it['deskripsi'] ?? '')], [3200, 6800]);
        }

        $this->subJudul($section, 'Keterkaitan CPL × Bahan Kajian');
        $table2 = $this->tabel($section);
        $this->baris($table2, ['CPL', 'Bahan Kajian Penopang'], [1500, 8500], true);
        foreach ($matriksCplBk as $row) {
            $daftar = implode('; ', $row['bahan_kajian'] ?? []);
            $this->baris($table2, [(string) ($row['cpl'] ?? ''), $daftar !== '' ? $daftar : '—'], [1500, 8500]);
        }
    }

    private function addStrukturMk(Section $section, array $perSemester): void
    {
        foreach ($perSemester as $grup) {
            $sem = $grup['semester'] ?? null;
            $section->addTextBreak(1);
            $section->addText(
                $sem !== null ? 'Semester ' . $sem : 'Tanpa Semester',
                ['bold' => true, 'size' => 11, 'color' => self::BRAND]
            );
            $table = $this->tabel($section);
            $this->baris($table, ['Kode', 'Nama Mata Kuliah', 'SKS', 'Sifat', 'Jenis'], [1300, 5200, 900, 1300, 1300], true);
            foreach ($grup['mata_kuliah'] ?? [] as $mk) {
                $this->baris($table, [
                    (string) ($mk['kode_mk'] ?? ''),
                    (string) ($mk['nama'] ?? ''),
                    (string) ($mk['sks'] ?? 0),
                    (string) ($mk['sifat'] ?? '-'),
                    (string) ($mk['jenis_mk'] ?? '-'),
                ], [1300, 5200, 900, 1300, 1300]);
            }
        }
    }

    private function addRpsRingkas(Section $section, array $items): void
    {
        $table = $this->tabel($section);
        $this->baris($table, ['Kode', 'Mata Kuliah', 'Versi', 'CPMK', 'Sub-CPMK', 'Pekan', 'Komponen'], [1200, 4000, 900, 900, 1100, 900, 1100], true);
        foreach ($items as $it) {
            $this->baris($table, [
                (string) ($it['kode_mk'] ?? ''),
                (string) ($it['nama'] ?? ''),
                (string) ($it['versi'] ?? 0),
                (string) ($it['jumlah_cpmk'] ?? 0),
                (string) ($it['jumlah_sub_cpmk'] ?? 0),
                (string) ($it['jumlah_minggu'] ?? 0),
                (string) ($it['jumlah_komponen'] ?? 0),
            ], [1200, 4000, 900, 900, 1100, 900, 1100]);
        }
    }

    /**
     * Matriks centang generik: baris = entitas, kolom = daftar kode CPL.
     *
     * @param  list<array<string,mixed>>  $rows
     * @param  list<string>  $kolom
     */
    private function addMatriksKode(Section $section, array $rows, string $labelKey, string $nilaiKey, array $kolom, string $labelHead): void
    {
        if ($kolom === [] || $rows === []) {
            $this->paragraf($section, 'Belum ada data pemetaan.');
            return;
        }

        $labelW = 3000;
        $sisa = 10000 - $labelW;
        $colW = max(500, (int) floor($sisa / max(1, count($kolom))));

        $table = $this->tabel($section);
        $header = array_merge([$labelHead], $kolom);
        $widths = array_merge([$labelW], array_fill(0, count($kolom), $colW));
        $this->baris($table, $header, $widths, true, Jc::CENTER);

        foreach ($rows as $row) {
            $table->addRow();
            $c = $table->addCell($labelW);
            $c->addText((string) ($row[$labelKey] ?? ''), ['size' => 9, 'bold' => true], ['spaceAfter' => 0]);
            $terpetakan = array_map('strval', $row[$nilaiKey] ?? []);
            foreach ($kolom as $kode) {
                $cell = $table->addCell($colW, ['valign' => 'center']);
                $cell->addText(
                    in_array($kode, $terpetakan, true) ? '✓' : '',
                    ['size' => 10, 'bold' => true, 'color' => self::HIT],
                    ['alignment' => Jc::CENTER, 'spaceAfter' => 0]
                );
            }
        }
    }

    // ------------------------------------------------------------------ Util

    /** @return list<string> */
    private function kodeCpl(array $cpl): array
    {
        return array_values(array_map(fn($c) => (string) ($c['kode'] ?? ''), $cpl));
    }

    private function siapkanMkCpl(array $rows): array
    {
        return array_map(fn($r) => [
            'label' => trim(((string) ($r['kode_mk'] ?? '')) . ' — ' . ((string) ($r['nama'] ?? ''))),
            'cpl'   => $r['cpl'] ?? [],
        ], $rows);
    }

    private function bab(Section $section, string $nomor, string $judul): void
    {
        $section->addTextBreak(1);
        $section->addText($nomor . '. ' . $judul, ['bold' => true, 'size' => 14, 'color' => self::BRAND], ['spaceAfter' => 120]);
    }

    private function judulTanpaNomor(Section $section, string $judul): void
    {
        $section->addText($judul, ['bold' => true, 'size' => 14, 'color' => self::BRAND], ['spaceAfter' => 120]);
    }

    private function subJudul(Section $section, string $judul): void
    {
        $section->addTextBreak(1);
        $section->addText($judul, ['bold' => true, 'size' => 11, 'color' => self::INK], ['spaceAfter' => 80]);
    }

    private function naratifAtauPlaceholder(Section $section, ?string $narasi, string $panduan): void
    {
        $narasi = trim((string) $narasi);
        if ($narasi !== '') {
            $this->paragraf($section, $narasi);
            return;
        }
        $section->addText(
            '[Bagian ini dilengkapi oleh program studi] ' . $panduan,
            ['size' => 10, 'italic' => true, 'color' => self::MUTED],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 120]
        );
    }

    private function paragraf(Section $section, string $teks): void
    {
        foreach (preg_split('/\n{2,}/', trim($teks)) as $par) {
            $par = trim($par);
            if ($par !== '') {
                $section->addText($par, ['size' => 11, 'color' => self::INK], ['alignment' => Jc::BOTH, 'spaceAfter' => 120]);
            }
        }
    }

    private function tabel(Section $section)
    {
        return $section->addTable([
            'borderSize'  => 6,
            'borderColor' => self::LINE,
            'cellMargin'  => 60,
            'width'       => 100 * 50,
            'unit'        => TblWidth::PERCENT,
            'alignment'   => JcTable::CENTER,
        ]);
    }

    /**
     * @param  list<string>  $sel
     * @param  list<int>  $widths
     */
    private function baris($table, array $sel, array $widths, bool $header = false, string $align = Jc::START): void
    {
        $table->addRow(null, $header ? ['tblHeader' => true] : null);
        foreach ($sel as $i => $teks) {
            $w = $widths[$i] ?? 1500;
            $cell = $table->addCell($w, $header ? ['bgColor' => self::HEAD_FILL, 'valign' => 'center'] : ['valign' => 'center']);
            $cell->addText(
                (string) $teks,
                ['size' => 9, 'bold' => $header, 'color' => self::INK],
                ['alignment' => $align, 'spaceAfter' => 0]
            );
        }
    }
}
