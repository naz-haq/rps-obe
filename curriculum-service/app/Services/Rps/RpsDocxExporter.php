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
            'komponenPenilaian.subCpmk.cpmk',
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

        $konteks = app(RpsPrintContext::class)->build($rps);

        $phpWord = new PhpWord();
        \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(9);

        $section = $phpWord->addSection([
            'orientation'  => 'landscape',
            'marginTop'    => 700,
            'marginBottom' => 700,
            'marginLeft'   => 700,
            'marginRight'  => 700,
        ]);

        $this->addKop($section, $konteks, $rps, $institusi);
        $this->addIdentitasKpt($section, $rps, $mk);
        $this->addOtorisasi($section);
        $this->addCapaian($section, $cplDiampu, $konteks);
        $this->addKorelasi($section, $konteks);
        $this->addDeskripsiSingkat($section, $mk);
        $this->addBahanKajian($section, $konteks);
        $this->addPustaka($section, $konteks);
        $this->addPengampu($section, $konteks);
        $this->addPrasyarat($section, $konteks);
        $this->addMingguan($section, $minggu, $komponen);
        $this->addKomponen($section, $komponen);
        $this->addRubrik($section, $komponen);

        $section->addTextBreak(1);
        $section->addText(
            'Dokumen mengikuti template Panduan Penyusunan KPT 2024 Direktorat Belmawa · Dicetak ' . now()->format('d M Y H:i'),
            ['size' => 7, 'italic' => true, 'color' => self::MUTED],
            ['alignment' => Jc::END]
        );

        return $phpWord;
    }

    private function addKop(Section $section, array $konteks, RpsVersion $rps, ?Institusi $institusi): void
    {
        $univ = $konteks['universitas']['nama'] ?? $institusi?->nama ?? 'Institusi';
        $fak  = $konteks['fakultas']['nama'] ?? null;
        $prod = $konteks['prodi']['nama'] ?? null;
        $kodeDok = $rps->kode_dokumen ?? '—';

        $table = $section->addTable([
            'borderSize'  => 6,
            'borderColor' => self::LINE,
            'cellMargin'  => 60,
            'alignment'   => JcTable::CENTER,
            'width'       => 100 * 50,
            'unit'        => \PhpOffice\PhpWord\SimpleType\TblWidth::PERCENT,
        ]);
        $table->addRow(null, ['tblHeader' => true]);
        $c1 = $table->addCell(2200, ['valign' => 'center']);
        $c1->addText('LOGO', ['bold' => true, 'size' => 10, 'color' => self::MUTED], ['alignment' => Jc::CENTER]);

        $c2 = $table->addCell(11000, ['valign' => 'center']);
        $c2->addText(strtoupper($univ), ['bold' => true, 'size' => 12], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        if ($fak) {
            $c2->addText(strtoupper($fak), ['bold' => true, 'size' => 10], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        }
        if ($prod) {
            $c2->addText('Program Studi ' . $prod, ['size' => 10], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
        }
        $c2->addText('RENCANA PEMBELAJARAN SEMESTER (RPS)', ['bold' => true, 'size' => 11], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);

        $c3 = $table->addCell(2600, ['valign' => 'center']);
        $c3->addText('Kode Dokumen', ['bold' => true, 'size' => 8, 'color' => self::MUTED], ['alignment' => Jc::CENTER]);
        $c3->addText($kodeDok, ['bold' => true, 'size' => 10], ['alignment' => Jc::CENTER]);

        $section->addTextBreak(1);
    }

    private function addIdentitasKpt(Section $section, RpsVersion $rps, ?MataKuliah $mk): void
    {
        $sksTeori = $mk?->sks_teori ?? 0;
        $sksPraktik = $mk?->sks_praktik ?? 0;
        $sksTotal = $mk?->sks ?? ($sksTeori + $sksPraktik);

        $table = $this->newTable($section);

        $head = ['Nama Mata Kuliah', 'Kode', 'Rumpun MK', 'Bobot (SKS)', 'Semester', 'Tgl Penyusunan', 'Sifat'];
        $widths = [3200, 1400, 1800, 2400, 1200, 1800, 1400];
        $this->headRow($table, $head, $widths);

        $table->addRow();
        $this->dataCell($table, (string) ($mk?->nama ?? '—'), $widths[0]);
        $this->dataCell($table, (string) $rps->kode_mk, $widths[1], Jc::CENTER);
        $this->dataCell($table, (string) ($mk?->rumpun ?? '—'), $widths[2]);
        $this->dataCell($table, sprintf('%s (T=%s P=%s)', $sksTotal, $sksTeori, $sksPraktik), $widths[3], Jc::CENTER);
        $this->dataCell($table, (string) ($mk?->semester ?? '—'), $widths[4], Jc::CENTER);
        $this->dataCell($table, optional($rps->tanggal_penyusunan)->format('d M Y') ?? '—', $widths[5], Jc::CENTER);
        $this->dataCell($table, $mk?->sifat ? ucfirst($mk->sifat) : '—', $widths[6], Jc::CENTER);
    }

    private function addOtorisasi(Section $section): void
    {
        $this->addHeading($section, 'Otorisasi / Pengesahan');
        $table = $this->newTable($section);
        $widths = [4400, 4400, 4400];
        $this->headRow($table, ['Pengembang RPS', 'Koordinator RMK', 'Ka Prodi'], $widths);

        $table->addRow(1400);
        foreach ($widths as $w) {
            $cell = $table->addCell($w, ['valign' => 'bottom']);
            $cell->addText(' ', ['size' => 8]);
            $cell->addText(' ', ['size' => 8]);
            $cell->addText(' ', ['size' => 8]);
            $cell->addText('(_______________________)', ['size' => 8], ['alignment' => Jc::CENTER]);
        }
    }

    private function addCapaian(Section $section, $cplDiampu, array $konteks): void
    {
        $this->addHeading($section, 'Capaian Pembelajaran (CP)');

        $section->addText('CPL yang Dibebankan pada MK', ['bold' => true, 'size' => 9], ['spaceBefore' => 80, 'spaceAfter' => 40]);
        if ($cplDiampu->isEmpty()) {
            $section->addText('Belum ada CPL tertaut pada RPS ini.', ['size' => 9, 'color' => self::MUTED], ['spaceAfter' => 60]);
        } else {
            foreach ($cplDiampu as $cpl) {
                $p = $section->addTextRun(['spaceAfter' => 30]);
                $p->addText($cpl->kode . '  ', ['bold' => true, 'color' => self::BRAND, 'size' => 9]);
                $p->addText((string) $cpl->deskripsi, ['size' => 9]);
            }
        }

        $section->addText('Capaian Pembelajaran Mata Kuliah (CPMK)', ['bold' => true, 'size' => 9], ['spaceBefore' => 100, 'spaceAfter' => 40]);
        if (empty($konteks['cpmk_list'])) {
            $section->addText('Belum ada CPMK.', ['size' => 9, 'color' => self::MUTED], ['spaceAfter' => 60]);
        } else {
            foreach ($konteks['cpmk_list'] as $c) {
                $p = $section->addTextRun(['spaceAfter' => 30]);
                $p->addText(($c['kode'] ?? '—') . '  ', ['bold' => true, 'color' => self::BRAND, 'size' => 9]);
                $p->addText((string) ($c['deskripsi'] ?? ''), ['size' => 9]);
                if (! empty($c['kontribusi_persen'])) {
                    $p->addText('  (' . $this->angka($c['kontribusi_persen']) . '%)', ['bold' => true, 'size' => 8]);
                }
                if (! empty($c['bloom'])) {
                    $p->addText('  ' . $c['bloom'], ['size' => 8, 'color' => self::MUTED]);
                }
            }
        }

        $section->addText('Sub-CPMK', ['bold' => true, 'size' => 9], ['spaceBefore' => 100, 'spaceAfter' => 40]);
        if (empty($konteks['sub_cpmk_list'])) {
            $section->addText('Belum ada Sub-CPMK.', ['size' => 9, 'color' => self::MUTED], ['spaceAfter' => 60]);
        } else {
            foreach ($konteks['sub_cpmk_list'] as $s) {
                $p = $section->addTextRun(['spaceAfter' => 30]);
                $p->addText(($s['kode'] ?? '—') . '  ', ['bold' => true, 'color' => self::BRAND, 'size' => 9]);
                $p->addText((string) ($s['deskripsi'] ?? ''), ['size' => 9]);
                if (! empty($s['cpmk'])) {
                    $p->addText('  · CPMK ' . $s['cpmk'], ['size' => 8, 'color' => self::MUTED]);
                }
                if (! empty($s['kontribusi_persen'])) {
                    $p->addText('  (' . $this->angka($s['kontribusi_persen']) . '%)', ['bold' => true, 'size' => 8]);
                }
                if (! empty($s['bloom'])) {
                    $p->addText('  ' . $s['bloom'], ['size' => 8, 'color' => self::MUTED]);
                }
            }
        }
    }

    private function addKorelasi(Section $section, array $konteks): void
    {
        $matriks = $konteks['matriks_korelasi'] ?? null;
        if (! $matriks || empty($matriks['cpl']) || empty($matriks['baris'])) {
            return;
        }
        $this->addHeading($section, 'Matriks Korelasi Sub-CPMK terhadap CPL');

        $table = $this->newTable($section);
        $cplCount = count($matriks['cpl']);
        $head = ['Sub-CPMK'];
        $widths = [2000];
        $colW = (int) max(600, (10800 - 2000 - 1600) / max(1, $cplCount));
        foreach ($matriks['cpl'] as $c) {
            $head[] = $c['kode'] . ' (%)';
            $widths[] = $colW;
        }
        $head[] = 'Bobot Penilaian (%)';
        $widths[] = 1600;
        $this->headRow($table, $head, $widths, Jc::CENTER);

        foreach ($matriks['baris'] as $row) {
            $table->addRow();
            $this->dataCell($table, (string) ($row['sub_cpmk'] ?? '—'), $widths[0]);
            $i = 1;
            foreach ($matriks['cpl'] as $c) {
                $b = $row['bobot_per_cpl'][$c['kode']] ?? null;
                $this->dataCell($table, $b !== null ? $this->angka($b) : '·', $widths[$i], Jc::CENTER);
                $i++;
            }
            $this->dataCell($table, isset($row['bobot_nilai']) && $row['bobot_nilai'] !== null ? $this->angka($row['bobot_nilai']) : '·', $widths[$i], Jc::CENTER);
        }
    }

    private function addDeskripsiSingkat(Section $section, ?MataKuliah $mk): void
    {
        if (! $mk?->deskripsi_singkat) {
            return;
        }
        $this->addHeading($section, 'Deskripsi Singkat Mata Kuliah');
        $section->addText($mk->deskripsi_singkat, ['size' => 9], ['spaceAfter' => 100]);
    }

    private function addBahanKajian(Section $section, array $konteks): void
    {
        $bk = $konteks['bahan_kajian'] ?? [];
        if (empty($bk)) {
            return;
        }
        $this->addHeading($section, 'Bahan Kajian / Materi Pembelajaran');
        $i = 1;
        foreach ($bk as $b) {
            $p = $section->addTextRun(['spaceAfter' => 30]);
            $p->addText($i . '. ', ['bold' => true, 'size' => 9]);
            $p->addText((string) ($b['nama'] ?? ''), ['bold' => true, 'size' => 9]);
            if (! empty($b['deskripsi'])) {
                $p->addText('  — ' . $b['deskripsi'], ['size' => 9, 'color' => self::MUTED]);
            }
            if (! empty($b['keterampilan'])) {
                $section->addText('     Keterampilan: ' . implode('; ', $b['keterampilan']), ['size' => 8, 'italic' => true, 'color' => self::MUTED], ['spaceAfter' => 30]);
            }
            $i++;
        }
    }

    private function addPustaka(Section $section, array $konteks): void
    {
        $utama = $konteks['pustaka_utama'] ?? [];
        $pend  = $konteks['pustaka_pendukung'] ?? [];
        if (empty($utama) && empty($pend)) {
            return;
        }
        $this->addHeading($section, 'Pustaka');

        if (! empty($utama)) {
            $section->addText('Utama', ['bold' => true, 'size' => 9], ['spaceBefore' => 60, 'spaceAfter' => 30]);
            $i = 1;
            foreach ($utama as $ref) {
                $section->addText($i . '. ' . $ref, ['size' => 9], ['spaceAfter' => 20]);
                $i++;
            }
        }
        if (! empty($pend)) {
            $section->addText('Pendukung', ['bold' => true, 'size' => 9], ['spaceBefore' => 80, 'spaceAfter' => 30]);
            $i = 1;
            foreach ($pend as $ref) {
                $section->addText($i . '. ' . $ref, ['size' => 9], ['spaceAfter' => 20]);
                $i++;
            }
        }
    }

    private function addPengampu(Section $section, array $konteks): void
    {
        $this->addHeading($section, 'Dosen Pengampu');
        $pengampu = $konteks['pengampu'] ?? [];
        if (empty($pengampu)) {
            $section->addText('—', ['size' => 9, 'color' => self::MUTED], ['spaceAfter' => 60]);
            return;
        }
        $i = 1;
        foreach ($pengampu as $p) {
            $line = $i . '. ' . ($p['nama'] ?? '—');
            if (! empty($p['nidn'])) {
                $line .= ' (NIDN ' . $p['nidn'] . ')';
            }
            if (! empty($p['peran'])) {
                $line .= ' — ' . $p['peran'];
            }
            $section->addText($line, ['size' => 9], ['spaceAfter' => 20]);
            $i++;
        }
    }

    private function addPrasyarat(Section $section, array $konteks): void
    {
        $this->addHeading($section, 'Mata Kuliah Syarat');
        $pr = $konteks['prasyarat'] ?? null;
        if (! $pr) {
            $section->addText('—', ['size' => 9, 'color' => self::MUTED], ['spaceAfter' => 60]);
            return;
        }
        $section->addText(
            trim(($pr['kode'] ?? '') . ' — ' . ($pr['nama'] ?? '')),
            ['size' => 9],
            ['spaceAfter' => 60]
        );
    }

    private function addMingguan(Section $section, $minggu, $komponen): void
    {
        $this->addHeading($section, 'Rencana Pembelajaran Mingguan (Format KPT 2024)');
        $table = $this->newTable($section);

        $head = ['Mg', 'Sub-CPMK', 'Indikator', 'Kriteria & Bentuk Penilaian', 'Bentuk Pembelajaran — Luring', 'Bentuk Pembelajaran — Daring', 'Materi Pembelajaran [Pustaka]', 'Bobot (%)'];
        $widths = [500, 1500, 1900, 2100, 2000, 2000, 2000, 900];
        $this->headRow($table, $head, $widths);

        if ($minggu->isEmpty()) {
            $table->addRow();
            $cell = $table->addCell(array_sum($widths), ['gridSpan' => count($head)]);
            $cell->addText('Belum ada rencana mingguan.', ['size' => 9, 'color' => self::MUTED], ['alignment' => Jc::CENTER]);
            return;
        }

        foreach ($minggu as $m) {
            $isUts = str_contains(strtolower((string) $m->materi_pustaka), 'uts') || str_contains(strtolower((string) $m->materi_pustaka), 'ujian tengah');
            $isUas = str_contains(strtolower((string) $m->materi_pustaka), 'uas') || str_contains(strtolower((string) $m->materi_pustaka), 'ujian akhir');
            if ($isUts || $isUas) {
                $table->addRow();
                $this->dataCell($table, (string) $m->minggu_ke, $widths[0], Jc::CENTER);
                $cell = $table->addCell(array_sum($widths) - $widths[0], ['gridSpan' => count($head) - 1, 'bgColor' => 'FEF3C7']);
                $cell->addText(strtoupper($isUts ? 'Evaluasi Tengah Semester (UTS)' : 'Evaluasi Akhir Semester (UAS)'), ['bold' => true, 'size' => 9], ['alignment' => Jc::CENTER]);
                continue;
            }

            $ctx = app(RpsPrintContext::class);
            $waktuTeks = $ctx->formatEstimasi($m->estimasi_waktu);
            $luring = trim((string) $m->bentuk_luring);
            $daring = trim((string) $m->bentuk_daring);
            if ($luring === '' && $daring === '' && $m->metode_pembelajaran) {
                $luring = $m->metode_pembelajaran;
            }
            $kriteria = $ctx->formatKriteria($m->teknik_kriteria_penilaian);

            $table->addRow();
            $this->dataCell($table, (string) $m->minggu_ke, $widths[0], Jc::CENTER);
            $this->dataCell($table, $this->subCpmkLabel($m->subCpmk), $widths[1]);
            $this->dataCell($table, $m->indikator ?? '—', $widths[2]);

            $cellKrit = $table->addCell($widths[3]);
            foreach (explode("\n", $kriteria !== '' ? $kriteria : '—') as $baris) {
                $cellKrit->addText($baris, ['size' => 8]);
            }

            $cellLuring = $table->addCell($widths[4]);
            $cellLuring->addText($luring !== '' ? $luring : '—', ['size' => 8]);
            if ($waktuTeks !== '') {
                $cellLuring->addText($waktuTeks, ['size' => 7, 'italic' => true, 'color' => self::MUTED]);
            }

            $this->dataCell($table, $daring !== '' ? $daring : '—', $widths[5]);
            $this->dataCell($table, $m->materi_pustaka ?? '—', $widths[6]);
            $this->dataCell($table, $this->angka($m->bobot_penilaian), $widths[7], Jc::CENTER);
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
            $this->dataCell($table, $this->subCpmkLabel($k->subCpmk), $widths[3]);
            $this->dataCell($table, $k->minggu_ke !== null ? (string) $k->minggu_ke : '—', $widths[4], Jc::CENTER);
            $this->dataCell($table, $this->angka($k->bobot_persen), $widths[5], Jc::CENTER);
        }

        $table->addRow();
        $cell = $table->addCell(array_sum(array_slice($widths, 0, 5)), ['gridSpan' => 5, 'bgColor' => self::HEAD_FILL]);
        $cell->addText('Total', ['bold' => true, 'size' => 9]);
        $this->dataCell($table, $this->angka($total), $widths[5], Jc::CENTER, true);
    }

    private function subCpmkLabel($subCpmk): string
    {
        if (! $subCpmk) {
            return '—';
        }

        $text = (string) ($subCpmk->kode ?? '');
        $desc = trim((string) ($subCpmk->deskripsi ?? ''));
        if ($desc !== '') {
            $text .= ' - ' . $desc;
        }

        if ($subCpmk->cpmk) {
            $text .= ' | CPMK ' . $subCpmk->cpmk->kode;
            $cpmkDesc = trim((string) ($subCpmk->cpmk->deskripsi ?? ''));
            if ($cpmkDesc !== '') {
                $text .= ': ' . $cpmkDesc;
            }
        }

        return trim($text) !== '' ? $text : '—';
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

    private function headRow($table, array $head, array $widths, string $align = Jc::START): void
    {
        $table->addRow(null, ['tblHeader' => true]);
        foreach ($head as $i => $h) {
            $cell = $table->addCell($widths[$i], ['bgColor' => self::HEAD_FILL]);
            $cell->addText($h, ['bold' => true, 'size' => 8, 'color' => self::INK], ['alignment' => $align]);
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
