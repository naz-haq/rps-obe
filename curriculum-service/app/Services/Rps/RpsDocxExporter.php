<?php

namespace App\Services\Rps;

use App\Models\Institusi;
use App\Models\MataKuliah;
use App\Models\RpsVersion;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;

/**
 * Membangun dokumen RPS dalam format Word (.docx) asli via PhpWord.
 * Konten mengikuti dokumen cetak HTML (resources/views/rps/cetak.blade.php).
 */
class RpsDocxExporter
{
    private const INK = '1E293B';
    private const MUTED = '64748B';
    private const BRAND = '2563EB';
    private const HEAD_FILL = 'F1F5F9';
    private const LINE = 'CBD5E1';

    /** Bangun objek PhpWord berisi RPS lengkap. */
    public function build(RpsVersion $rps): PhpWord
    {
        $rps->loadMissing([
            'minggu.subCpmk.cpmk.cpl',
            'komponenPenilaian.subCpmk',
            'komponenPenilaian.rubrik.kriteria',
        ]);

        $mk = MataKuliah::where('kode_mk', $rps->kode_mk)
            ->where('institusi_id', $rps->institusi_id)
            ->first();
        $institusi = Institusi::find($rps->institusi_id);

        $minggu = $rps->minggu->sortBy('minggu_ke')->values();
        $komponen = $rps->komponenPenilaian->values();
        $cplDiampu = $rps->minggu
            ->flatMap(fn($m) => $m->subCpmk?->cpmk ? $m->subCpmk->cpmk->cpl : collect())
            ->unique('id')
            ->values();

        $phpWord = new PhpWord();
        // PhpWord tidak meng-escape karakter XML (&, <, >) secara default → dokumen korup
        // bila teks memuat '&'. Aktifkan escaping agar document.xml selalu well-formed.
        \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(10);

        $section = $phpWord->addSection([
            'orientation'  => 'landscape',
            'marginTop'    => 700,
            'marginBottom' => 700,
            'marginLeft'   => 700,
            'marginRight'  => 700,
        ]);

        $this->addKop($section, $institusi?->nama ?? 'Institusi');
        $this->addJudul($section, $rps, $mk);
        $this->addIdentitas($section, $rps, $mk);

        if ($mk?->deskripsi_singkat) {
            $this->addHeading($section, 'Deskripsi Mata Kuliah');
            $section->addText($mk->deskripsi_singkat, ['size' => 10], ['spaceAfter' => 120]);
        }

        $this->addCpl($section, $cplDiampu);
        $this->addMingguan($section, $minggu);
        $this->addKomponen($section, $komponen);
        $this->addRubrik($section, $komponen);

        $section->addTextBreak(1);
        $section->addText(
            'Dicetak ' . now()->format('d M Y H:i'),
            ['size' => 8, 'italic' => true, 'color' => self::MUTED],
            ['alignment' => Jc::END]
        );

        return $phpWord;
    }

    private function addKop(Section $section, string $institusi): void
    {
        $section->addText($institusi, ['bold' => true, 'size' => 13], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        $section->addText(
            'Rencana Pembelajaran Semester (RPS)',
            ['size' => 10, 'color' => self::MUTED],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 40]
        );
        $section->addTextBreak(1);
    }

    private function addJudul(Section $section, RpsVersion $rps, ?MataKuliah $mk): void
    {
        $section->addText($mk?->nama ?? $rps->kode_mk, ['bold' => true, 'size' => 15, 'color' => self::INK], ['spaceAfter' => 20]);
        $section->addText(
            sprintf(
                'Kode: %s · Versi %s · Status %s · Bahasa %s',
                $rps->kode_mk,
                $rps->versi,
                ucfirst((string) $rps->status),
                strtoupper($rps->bahasa ?? 'id')
            ),
            ['size' => 9, 'color' => self::MUTED],
            ['spaceAfter' => 120]
        );
    }

    private function addIdentitas(Section $section, RpsVersion $rps, ?MataKuliah $mk): void
    {
        $this->addHeading($section, 'Identitas Mata Kuliah');

        $sksTeori = $mk->sks_teori ?? null;
        $sksPraktik = $mk->sks_praktik ?? null;
        $sksTotal = $mk?->sks ?? (($sksTeori ?? 0) + ($sksPraktik ?? 0));
        $sksTeks = $sksTotal;
        if ($sksTeori !== null || $sksPraktik !== null) {
            $sksTeks .= sprintf(' (T:%s / P:%s)', $sksTeori ?? 0, $sksPraktik ?? 0);
        }

        $table = $this->newTable($section);
        $rows = [
            ['Kode Mata Kuliah', $rps->kode_mk, 'SKS', (string) $sksTeks],
            ['Nama Mata Kuliah', $mk->nama ?? '—', 'Semester', (string) ($mk->semester ?? '—')],
            ['Rumpun', $mk->rumpun ?? '—', 'Sifat', $mk?->sifat ? ucfirst($mk->sifat) : '—'],
            ['Koordinator MK', $rps->koordinator_mk ?? '—', 'Tanggal Penyusunan', optional($rps->tanggal_penyusunan)->format('d M Y') ?? '—'],
        ];
        foreach ($rows as $r) {
            $table->addRow();
            $this->labelCell($table, $r[0], 2600);
            $this->valueCell($table, $r[1], 4700);
            $this->labelCell($table, $r[2], 2600);
            $this->valueCell($table, $r[3], 4700);
        }
    }

    private function addCpl(Section $section, $cplDiampu): void
    {
        $this->addHeading($section, 'CPL yang Diampu');
        if ($cplDiampu->isEmpty()) {
            $section->addText('Belum ada CPL tertaut pada RPS ini.', ['size' => 9, 'color' => self::MUTED], ['spaceAfter' => 80]);

            return;
        }
        foreach ($cplDiampu as $cpl) {
            $p = $section->addTextRun(['spaceAfter' => 40]);
            $p->addText($cpl->kode . '  ', ['bold' => true, 'color' => self::BRAND, 'size' => 9]);
            $p->addText((string) $cpl->deskripsi, ['size' => 9]);
        }
    }

    private function addMingguan(Section $section, $minggu): void
    {
        $this->addHeading($section, 'Rencana Pembelajaran Mingguan');
        $table = $this->newTable($section);

        $head = ['Mg', 'Sub-CPMK', 'Indikator', 'Kriteria & Teknik', 'Bentuk & Metode', 'Pengalaman Belajar', 'Materi & Pustaka', 'Estimasi Waktu', 'Bobot (%)'];
        $widths = [500, 1300, 1800, 1900, 2200, 1900, 1800, 1500, 900];
        $this->headRow($table, $head, $widths);

        if ($minggu->isEmpty()) {
            $table->addRow();
            $cell = $table->addCell(array_sum($widths), ['gridSpan' => count($head)]);
            $cell->addText('Belum ada rencana mingguan.', ['size' => 9, 'color' => self::MUTED], ['alignment' => Jc::CENTER]);

            return;
        }

        foreach ($minggu as $m) {
            $bentuk = collect([
                $m->metode_pembelajaran,
                $m->bentuk_luring ? 'Luring: ' . $m->bentuk_luring : null,
                $m->bentuk_daring ? 'Daring: ' . $m->bentuk_daring : null,
            ])->filter()->implode('; ');
            $waktu = is_array($m->estimasi_waktu) ? ($m->estimasi_waktu['teks'] ?? '') : (string) $m->estimasi_waktu;

            $table->addRow();
            $this->dataCell($table, (string) $m->minggu_ke, $widths[0], Jc::CENTER);
            $this->dataCell($table, $m->subCpmk?->kode ?? '—', $widths[1]);
            $this->dataCell($table, $m->indikator ?? '—', $widths[2]);
            $this->dataCell($table, $m->teknik_kriteria_penilaian ?? '—', $widths[3]);
            $this->dataCell($table, $bentuk !== '' ? $bentuk : '—', $widths[4]);
            $this->dataCell($table, $m->pengalaman_belajar ?? '—', $widths[5]);
            $this->dataCell($table, $m->materi_pustaka ?? '—', $widths[6]);
            $this->dataCell($table, $waktu !== '' ? $waktu : '—', $widths[7]);
            $this->dataCell($table, $this->angka($m->bobot_penilaian), $widths[8], Jc::CENTER);
        }
    }

    private function addKomponen(Section $section, $komponen): void
    {
        $this->addHeading($section, 'Komponen Penilaian');
        $table = $this->newTable($section);

        $head = ['Komponen', 'Jenis', 'Instrumen', 'Sub-CPMK', 'Minggu', 'Bobot (%)'];
        $widths = [3400, 1800, 2900, 1800, 1100, 1300];
        $this->headRow($table, $head, $widths);

        if ($komponen->isEmpty()) {
            $table->addRow();
            $cell = $table->addCell(array_sum($widths), ['gridSpan' => count($head)]);
            $cell->addText('Belum ada komponen penilaian.', ['size' => 9, 'color' => self::MUTED], ['alignment' => Jc::CENTER]);

            return;
        }

        $total = 0.0;
        foreach ($komponen as $k) {
            $total += (float) $k->bobot_persen;
            $table->addRow();
            $this->dataCell($table, (string) $k->nama, $widths[0]);
            $this->dataCell($table, $k->jenis ?? '—', $widths[1]);
            $this->dataCell($table, $k->instrumen ?? '—', $widths[2]);
            $this->dataCell($table, $k->subCpmk?->kode ?? '—', $widths[3]);
            $this->dataCell($table, $k->minggu_ke !== null ? (string) $k->minggu_ke : '—', $widths[4], Jc::CENTER);
            $this->dataCell($table, $this->angka($k->bobot_persen), $widths[5], Jc::CENTER);
        }

        $table->addRow();
        $cell = $table->addCell(array_sum(array_slice($widths, 0, 5)), ['gridSpan' => 5, 'bgColor' => self::HEAD_FILL]);
        $cell->addText('Total', ['bold' => true, 'size' => 9]);
        $this->dataCell($table, $this->angka($total), $widths[5], Jc::CENTER, true);
    }

    private function addRubrik(Section $section, $komponen): void
    {
        $ada = $komponen->contains(fn($k) => $k->rubrik && $k->rubrik->kriteria->isNotEmpty());
        if (! $ada) {
            return;
        }

        $this->addHeading($section, 'Rubrik Penilaian');

        foreach ($komponen as $k) {
            $r = $k->rubrik;
            if (! $r || $r->kriteria->isEmpty()) {
                continue;
            }

            $labels = is_array($r->label_skala) ? array_values($r->label_skala) : [];
            $levels = $r->jumlah_level_skala ?: (count($labels) ?: 4);

            $p = $section->addTextRun(['spaceBefore' => 120, 'spaceAfter' => 40]);
            $p->addText((string) $k->nama, ['bold' => true, 'size' => 10]);
            $p->addText('  — rubrik ' . $r->jenis, ['size' => 9, 'color' => self::MUTED]);

            $table = $this->newTable($section);
            $levelWidth = (int) max(1200, (11000 - 3600) / max(1, $levels));
            $head = ['Kriteria', 'Bobot (%)'];
            $widths = [2600, 1000];
            for ($i = 0; $i < $levels; $i++) {
                $head[] = $labels[$i] ?? ('Level ' . ($i + 1));
                $widths[] = $levelWidth;
            }
            $this->headRow($table, $head, $widths);

            foreach ($r->kriteria->sortBy('urutan') as $kr) {
                $desk = is_array($kr->deskriptor) ? array_values($kr->deskriptor) : [];
                $table->addRow();
                $this->dataCell($table, (string) $kr->kriteria, $widths[0]);
                $this->dataCell($table, $this->angka($kr->bobot), $widths[1], Jc::CENTER);
                for ($i = 0; $i < $levels; $i++) {
                    $this->dataCell($table, $desk[$i] ?? '—', $widths[2 + $i]);
                }
            }
        }
    }

    // ---- helper penataan ----

    private function addHeading(Section $section, string $teks): void
    {
        $section->addText(
            strtoupper($teks),
            ['bold' => true, 'size' => 10, 'color' => self::MUTED],
            ['spaceBefore' => 200, 'spaceAfter' => 60, 'borderBottomSize' => 8, 'borderBottomColor' => self::LINE]
        );
    }

    private function newTable(Section $section)
    {
        return $section->addTable([
            'borderSize'  => 6,
            'borderColor' => self::LINE,
            'cellMargin'  => 60,
            'alignment'   => JcTable::CENTER,
            'width'       => 100 * 50,
            'unit'        => \PhpOffice\PhpWord\SimpleType\TblWidth::PERCENT,
        ]);
    }

    private function headRow($table, array $head, array $widths): void
    {
        $table->addRow(null, ['tblHeader' => true]);
        foreach ($head as $i => $h) {
            $cell = $table->addCell($widths[$i], ['bgColor' => self::HEAD_FILL]);
            $cell->addText($h, ['bold' => true, 'size' => 8, 'color' => self::INK]);
        }
    }

    private function dataCell($table, string $text, int $width, string $align = Jc::START, bool $bold = false): void
    {
        $cell = $table->addCell($width);
        $cell->addText($text, ['size' => 8, 'bold' => $bold], ['alignment' => $align]);
    }

    private function labelCell($table, string $text, int $width): void
    {
        $cell = $table->addCell($width, ['bgColor' => self::HEAD_FILL]);
        $cell->addText($text, ['bold' => true, 'size' => 9]);
    }

    private function valueCell($table, string $text, int $width): void
    {
        $cell = $table->addCell($width);
        $cell->addText($text, ['size' => 9]);
    }

    private function angka($nilai): string
    {
        if ($nilai === null || $nilai === '') {
            return '—';
        }

        return rtrim(rtrim(number_format((float) $nilai, 2), '0'), '.');
    }
}
