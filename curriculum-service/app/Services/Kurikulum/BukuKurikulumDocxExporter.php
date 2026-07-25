<?php

namespace App\Services\Kurikulum;

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\SimpleType\TblWidth;

/**
 * Membangun Buku Kurikulum dalam format Word (.docx) via PhpWord.
 *
 * Menerima struktur deterministik dari BukuKurikulumBuilder dan (opsional) peta
 * narasi AI per bagian. Tabel/angka selalu dari data; narasi hanya melengkapi
 * prosa. Dokumen sengaja tanpa template khusus — prodi masih akan melengkapinya.
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
            $this->bab($section, 'Kata Pengantar');
            $this->paragraf($section, (string) $naratif['pengantar']);
        }

        $this->addProfilLulusan($section, $buku['profil_lulusan'] ?? [], $naratif['profil_lulusan'] ?? null);
        $this->addCpl($section, $buku['cpl'] ?? [], $naratif['cpl'] ?? null);
        $this->addMatriksKode(
            $section,
            'Matriks Profil Lulusan × CPL',
            $buku['matriks_pl_cpl'] ?? [],
            'profil',
            'cpl',
            $this->kodeCpl($buku['cpl'] ?? []),
        );
        $this->addBahanKajian($section, $buku['bahan_kajian'] ?? [], $buku['matriks_cpl_bk'] ?? []);
        $this->addStrukturMk($section, $buku['mata_kuliah'] ?? [], $naratif['mata_kuliah'] ?? null);
        $this->addMatriksKode(
            $section,
            'Matriks CPL × Mata Kuliah',
            $this->siapkanMkCpl($buku['matriks_mk_cpl'] ?? []),
            'label',
            'cpl',
            $this->kodeCpl($buku['cpl'] ?? []),
        );
        $this->addRpsRingkas($section, $buku['rps_ringkas'] ?? []);

        $section->addTextBreak(1);
        $section->addText(
            'Dokumen Buku Kurikulum dirakit otomatis dari data kurikulum · ' . now()->format('d M Y H:i')
                . ' · Silakan lengkapi dan sunting sesuai kebutuhan program studi.',
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
        $section->addText('BUKU KURIKULUM', ['bold' => true, 'size' => 22, 'color' => self::BRAND], ['alignment' => Jc::CENTER]);
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

    // ------------------------------------------------------------------ Bab-bab

    private function addProfilLulusan(Section $section, array $items, ?string $narasi): void
    {
        $this->bab($section, 'Profil Lulusan');
        if ($narasi) {
            $this->paragraf($section, $narasi);
        }
        $table = $this->tabel($section);
        $this->baris($table, ['Kode', 'Deskripsi Profil Lulusan'], [1500, 8500], true);
        foreach ($items as $it) {
            $this->baris($table, [(string) ($it['kode'] ?? ''), (string) ($it['deskripsi'] ?? '')], [1500, 8500]);
        }
    }

    private function addCpl(Section $section, array $items, ?string $narasi): void
    {
        $this->bab($section, 'Capaian Pembelajaran Lulusan (CPL)');
        if ($narasi) {
            $this->paragraf($section, $narasi);
        }
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
        $this->bab($section, 'Bahan Kajian');
        $table = $this->tabel($section);
        $this->baris($table, ['Bahan Kajian', 'Deskripsi'], [3200, 6800], true);
        foreach ($bk as $it) {
            $this->baris($table, [(string) ($it['nama'] ?? ''), (string) ($it['deskripsi'] ?? '')], [3200, 6800]);
        }

        $section->addTextBreak(1);
        $section->addText('Keterkaitan CPL × Bahan Kajian', ['bold' => true, 'size' => 11, 'color' => self::INK]);
        $table2 = $this->tabel($section);
        $this->baris($table2, ['CPL', 'Bahan Kajian Penopang'], [1500, 8500], true);
        foreach ($matriksCplBk as $row) {
            $daftar = implode('; ', $row['bahan_kajian'] ?? []);
            $this->baris($table2, [(string) ($row['cpl'] ?? ''), $daftar !== '' ? $daftar : '—'], [1500, 8500]);
        }
    }

    private function addStrukturMk(Section $section, array $perSemester, ?string $narasi): void
    {
        $this->bab($section, 'Struktur Mata Kuliah');
        if ($narasi) {
            $this->paragraf($section, $narasi);
        }
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
        $this->bab($section, 'Ringkasan RPS per Mata Kuliah');
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
     * @param  list<array<string,mixed>>  $rows    tiap row punya key label + array kode
     * @param  list<string>  $kolom  daftar kode CPL sebagai kolom
     */
    private function addMatriksKode(Section $section, string $judul, array $rows, string $labelKey, string $nilaiKey, array $kolom): void
    {
        $this->bab($section, $judul);
        if ($kolom === [] || $rows === []) {
            $this->paragraf($section, 'Belum ada data pemetaan.');
            return;
        }

        $labelW = 3000;
        $sisa = 10000 - $labelW;
        $colW = max(500, (int) floor($sisa / max(1, count($kolom))));

        $table = $this->tabel($section);
        $header = array_merge([''], $kolom);
        $widths = array_merge([$labelW], array_fill(0, count($kolom), $colW));
        $this->baris($table, $header, $widths, true, Jc::CENTER);

        foreach ($rows as $row) {
            $table->addRow();
            $c = $table->addCell($labelW);
            $c->addText((string) ($row[$labelKey] ?? ''), ['size' => 9, 'bold' => true], ['spaceAfter' => 0]);
            $terpetakan = array_map('strval', $row[$nilaiKey] ?? []);
            foreach ($kolom as $kode) {
                $cell = $table->addCell($colW, ['valign' => 'center']);
                $ada = in_array($kode, $terpetakan, true);
                $cell->addText(
                    $ada ? '✓' : '',
                    ['size' => 10, 'bold' => true, 'color' => self::HIT],
                    ['alignment' => Jc::CENTER, 'spaceAfter' => 0]
                );
            }
        }
    }

    // ------------------------------------------------------------------ Util

    /** @return list<string> kode CPL sebagai kolom matriks */
    private function kodeCpl(array $cpl): array
    {
        return array_values(array_map(fn($c) => (string) ($c['kode'] ?? ''), $cpl));
    }

    /** Normalisasi matriks MK×CPL: gabung kode+nama sebagai label baris. */
    private function siapkanMkCpl(array $rows): array
    {
        return array_map(fn($r) => [
            'label' => trim(((string) ($r['kode_mk'] ?? '')) . ' — ' . ((string) ($r['nama'] ?? ''))),
            'cpl'   => $r['cpl'] ?? [],
        ], $rows);
    }

    private function bab(Section $section, string $judul): void
    {
        $section->addTextBreak(1);
        $section->addText($judul, ['bold' => true, 'size' => 14, 'color' => self::BRAND], ['spaceAfter' => 120]);
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
