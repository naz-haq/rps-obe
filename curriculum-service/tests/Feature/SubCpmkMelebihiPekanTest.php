<?php

namespace Tests\Feature;

use App\Models\GenerateSession;
use App\Models\Institusi;
use App\Models\Kurikulum;
use App\Models\MataKuliah;
use App\Services\Generator\GenerationContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Jumlah Sub-CPMK boleh melebihi pekan belajar; kelebihannya berbagi pekan. */
class SubCpmkMelebihiPekanTest extends TestCase
{
    use RefreshDatabase;

    private MataKuliah $mk;
    private GenerateSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $prodi = Institusi::create(['kode' => 'PR-LB', 'nama' => 'Prodi Lebih', 'jenis' => 'prodi']);
        $kur = Kurikulum::create(['institusi_id' => $prodi->id, 'kode' => 'KUR-LB', 'nama' => 'Kur Lebih', 'tahun' => '2026']);
        $this->mk = MataKuliah::create([
            'institusi_id' => $prodi->id,
            'kurikulum_id' => $kur->id,
            'kode_mk' => 'MK-LB',
            'nama' => 'MK Lebih',
            'jenis_mk' => 'murni',
            'pola' => 'reguler',
            'sks_teori' => 2,
            'sks_praktik' => 0,
            'semester' => 1,
            'jumlah_minggu' => 16,
        ]);
        $this->session = GenerateSession::create([
            'institusi_id' => $prodi->id,
            'mk_id' => $this->mk->id,
            'draf' => [],
        ]);
    }

    public function test_batas_sub_cpmk_tidak_lagi_14(): void
    {
        $limits = app(GenerationContract::class)->limits($this->mk);

        $this->assertSame(40, $limits['max_sub_cpmk']);
        $this->assertSame(16, $limits['total_weeks']);
        $this->assertSame(14, $limits['learning_weeks']);
        $this->assertSame(1, $limits['pekan_pengantar']);
        $this->assertSame(8, $limits['pekan_uts']);
        $this->assertSame(16, $limits['pekan_uas']);
    }

    public function test_18_sub_cpmk_lolos_gate_jumlah(): void
    {
        $rows = [];
        for ($i = 1; $i <= 18; $i++) {
            $rows[] = ['kode' => "Sub-CPMK-{$i}", 'deskripsi' => "Mahasiswa mampu melakukan hal {$i}."];
        }

        $errors = app(GenerationContract::class)
            ->violations('sub_cpmk', ['sub_cpmk' => $rows], $this->session, $this->mk, false);

        $this->assertSame([], $errors);
    }

    public function test_sub_cpmk_tambahan_menghitung_cakupan_induk(): void
    {
        $this->session->draf = ['sub_cpmk' => ['sub_cpmk' => [
            ['kode' => 'Sub-CPMK-1'],
            ['kode' => 'Sub-CPMK-2'],
        ]]];

        $baris = [
            'minggu_ke' => 2,
            'sub_cpmk_kode' => 'Sub-CPMK-1',
            'sub_cpmk_kode_tambahan' => ['Sub-CPMK-2'],
            'indikator' => 'Indikator.',
            'kriteria_penilaian' => "Kriteria: x.\nTeknik: y.",
            'materi_pustaka' => 'Materi.',
        ];

        $errors = app(GenerationContract::class)
            ->violations('mingguan', ['minggu' => [$baris]], $this->session, $this->mk, true);

        $this->assertNotContains('Induk belum tercakup: Sub-CPMK-2.', $errors);

        // Kode tambahan yang tidak dikenal tetap ditolak.
        $baris['sub_cpmk_kode_tambahan'] = ['Sub-CPMK-9'];
        $errors = app(GenerationContract::class)
            ->violations('mingguan', ['minggu' => [$baris]], $this->session, $this->mk, true);
        $this->assertContains('minggu.0.sub_cpmk_kode_tambahan: kode induk tidak dikenal Sub-CPMK-9.', $errors);
    }
}
