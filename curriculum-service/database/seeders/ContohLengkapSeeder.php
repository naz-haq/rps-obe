<?php

namespace Database\Seeders;

use App\Models\BadanRujukan;
use App\Models\BahanKajian;
use App\Models\ButirAcuan;
use App\Models\Cpl;
use App\Models\CplBahanKajian;
use App\Models\Cpmk;
use App\Models\CpmkCpl;
use App\Models\DokumenRujukan;
use App\Models\Dosen;
use App\Models\Indikator;
use App\Models\Institusi;
use App\Models\KerangkaAcuan;
use App\Models\Keterampilan;
use App\Models\KomponenPenilaian;
use App\Models\KonfigurasiAturan;
use App\Models\Kurikulum;
use App\Models\MataKuliah;
use App\Models\MkBahanKajian;
use App\Models\MkCpl;
use App\Models\MkKeterampilan;
use App\Models\MkPengampu;
use App\Models\PlCpl;
use App\Models\ProfilLulusan;
use App\Models\Referensi;
use App\Models\RpsMinggu;
use App\Models\RpsVersion;
use App\Models\Rubrik;
use App\Models\RubrikKriteria;
use App\Models\SubCpmk;
use App\Models\Taksonomi;
use App\Models\TargetCpl;
use App\Services\Rps\EstimasiWaktuService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu paket data contoh utuh (aturan -> kurikulum -> RPS) untuk Program Studi
 * Sarjana Farmasi. Membersihkan data domain terlebih dahulu, lalu menyemai satu
 * rantai OBE lengkap: aturan & pedoman, kurikulum (PL/CPL/BK), satu mata kuliah
 * ber-RPS penuh 16 minggu beserta penilaiannya.
 *
 * DIPERTAHANKAN (tidak dihapus): users, institusi, template_rps, taksonomi,
 * tabel permission/RBAC, serta kredensial/pengaturan & prompt AI.
 */
class ContohLengkapSeeder extends Seeder
{
    /** Institusi prodi tujuan (Sarjana Farmasi). */
    private int $institusiId = 5;

    private string $kodeMk = 'FAR201';

    public function run(): void
    {
        $this->bersihkanDataDomain();

        $prodi = $this->siapkanHierarkiInstitusi();
        $this->institusiId = $prodi->id;

        [$badanKpt, $dokumenKpt, $versiPedoman] = $this->seedAturan();
        $kurikulum = $this->seedKurikulum();
        [$profil, $cpl] = $this->seedProfilDanCpl($kurikulum);
        $bahanKajian = $this->seedBahanKajian($kurikulum, $cpl);
        $this->seedMataKuliah($kurikulum, $cpl, $bahanKajian);
        [$cpmk, $subCpmk] = $this->seedCpmk($cpl);
        $this->seedReferensi();
        $this->seedRps($versiPedoman, $subCpmk);

        $this->command?->info('Paket data contoh Sarjana Farmasi berhasil disemai.');
    }

    /**
     * Kosongkan seluruh tabel data domain (aturan, kurikulum, CPMK/RPS, turunan
     * OBE, log). Tidak menyentuh users, institusi, template_rps, taksonomi,
     * tabel permission, serta kredensial/pengaturan/prompt AI.
     */
    private function bersihkanDataDomain(): void
    {
        $tabel = [
            // penilaian & RPS
            'rubrik_kriteria',
            'rubrik',
            'komponen_penilaian',
            'rps_minggu',
            'rps_approval_log',
            'rps_version',
            'referensi',
            // cpmk
            'indikator',
            'sub_cpmk',
            'cpmk_cpl',
            'cpmk',
            // OBAEI / capaian
            'tindak_lanjut',
            'evaluasi_cpl',
            'capaian_mahasiswa',
            // kurikulum
            'mk_bahan_kajian',
            'mk_keterampilan',
            'mk_cpl',
            'mk_pengampu',
            'cpl_bahan_kajian',
            'keterampilan',
            'bahan_kajian',
            'pl_cpl',
            'target_cpl',
            'cpl',
            'profil_lulusan',
            'mata_kuliah',
            'dosen',
            'kurikulum',
            // aturan & rujukan
            'konfigurasi_aturan',
            'column_mapping',
            'dokumen_chunk',
            'butir_acuan',
            'kerangka_acuan',
            'versi_pedoman',
            'dokumen_rujukan',
            'badan_rujukan',
            // sesi generator, AI log, tata kelola, kepatuhan
            'generate_session',
            'ai_interaksi',
            'ai_validasi',
            'source_citation',
            'audit_log',
            'notifikasi',
            'pemenuhan_acuan',
            'validasi_overlap',
        ];

        Schema::disableForeignKeyConstraints();
        foreach ($tabel as $t) {
            if (Schema::hasTable($t)) {
                DB::table($t)->truncate();
            }
        }
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Pastikan hierarki Universitas > Fakultas > Program Studi lengkap sehingga
     * kop RPS memuat ketiganya. Tidak menghapus institusi yang ada.
     */
    private function siapkanHierarkiInstitusi(): Institusi
    {
        $universitas = Institusi::firstOrCreate(
            ['kode' => 'UNIV01'],
            ['nama' => 'Universitas Contoh Nusantara', 'jenis' => 'universitas', 'parent_id' => null],
        );

        // Prodi tujuan: pakai yang sudah ada (id target) atau prodi Farmasi mana pun.
        $prodi = Institusi::find($this->institusiId)
            ?? Institusi::where('jenis', 'prodi')->where('kode', '48201-PSSF')->first()
            ?? Institusi::create([
                'kode' => '48201-PSSF',
                'nama' => 'Sarjana Farmasi',
                'jenis' => 'prodi',
                'asosiasi_profesi' => 'APTFI',
            ]);

        // Fakultas: induk prodi yang sesungguhnya, atau fakultas yang ada, atau buat baru.
        $fakultas = ($prodi->parent_id ? Institusi::find($prodi->parent_id) : null)
            ?? Institusi::where('jenis', 'fakultas')->first()
            ?? Institusi::create(['kode' => 'FF', 'nama' => 'Fakultas Farmasi', 'jenis' => 'fakultas']);

        $fakultas->update(['parent_id' => $universitas->id]);
        $prodi->update(['parent_id' => $fakultas->id]);

        return $prodi;
    }

    /** @return array{0:BadanRujukan,1:DokumenRujukan,2:\App\Models\VersiPedoman} */
    private function seedAturan(): array
    {
        $kpt = BadanRujukan::create([
            'institusi_id' => null,
            'nama' => 'Kemdiktisaintek',
            'jenis' => 'pemerintah',
            'disiplin' => null,
        ]);
        BadanRujukan::create([
            'institusi_id' => null,
            'nama' => 'APTFI',
            'jenis' => 'asosiasi',
            'disiplin' => 'Farmasi',
        ]);
        BadanRujukan::create([
            'institusi_id' => null,
            'nama' => 'LAM-PTKes',
            'jenis' => 'akreditasi',
            'disiplin' => 'Kesehatan',
        ]);

        $dokumen = DokumenRujukan::create([
            'institusi_id' => $this->institusiId,
            'badan_rujukan_id' => $kpt->id,
            'jenis' => 'kpt',
            'judul' => 'Panduan Penyusunan Kurikulum Pendidikan Tinggi (KPT) 2024',
            'status_indexing' => 'selesai',
        ]);

        $versiPedoman = $dokumen->versiPedoman()->create([
            'versi' => '2024',
            'tanggal_berlaku' => '2024-01-01',
        ]);

        $kerangka = KerangkaAcuan::create([
            'badan_rujukan_id' => $kpt->id,
            'dokumen_id' => $dokumen->id,
            'nama' => 'KPT 2024',
            'versi' => '2024',
            'tanggal_berlaku' => '2024-01-01',
        ]);

        $butir = [
            ['profil_lulusan', 'PL', 'Menetapkan Profil Lulusan sesuai kebutuhan pemangku kepentingan.'],
            ['cpl', 'CPL', 'Merumuskan CPL mencakup aspek sikap, pengetahuan, dan keterampilan (SN-Dikti).'],
            ['bahan_kajian', 'BK', 'Menyusun Bahan Kajian yang menopang setiap CPL.'],
            ['struktur', 'STR', 'Membentuk mata kuliah dari bahan kajian beserta bobot SKS.'],
            ['aturan', 'ATR', 'Menetapkan 1 SKS teori setara 170 menit/minggu (50 TM + 60 PT + 60 BM).'],
        ];
        foreach ($butir as $i => [$kategori, $kode, $deskripsi]) {
            ButirAcuan::create([
                'kerangka_acuan_id' => $kerangka->id,
                'kategori' => $kategori,
                'kode' => $kode,
                'deskripsi' => $deskripsi,
                'wajib' => true,
                'urutan' => $i + 1,
            ]);
        }

        $konfigurasi = [
            'jumlah_minggu' => ['minggu_efektif' => 16, 'minggu_evaluasi' => 2],
            'bobot_teori' => ['tatap_muka' => 50, 'terstruktur' => 60, 'mandiri' => 60],
            'bobot_praktikum' => ['praktik' => 170],
            'konversi_sks' => [
                'teori_tatap_muka' => 50,
                'teori_terstruktur' => 60,
                'teori_mandiri' => 60,
                'praktik' => 170,
            ],
        ];
        foreach ($konfigurasi as $jenis => $nilai) {
            KonfigurasiAturan::create([
                'institusi_id' => $this->institusiId,
                'badan_rujukan_id' => $kpt->id,
                'jenis_aturan' => $jenis,
                'nilai' => $nilai,
                'referensi_dokumen_id' => $dokumen->id,
            ]);
        }

        return [$kpt, $dokumen, $versiPedoman];
    }

    private function seedKurikulum(): Kurikulum
    {
        return Kurikulum::create([
            'institusi_id' => $this->institusiId,
            'kode' => 'KUR-SF-2024',
            'nama' => 'Kurikulum 2024 Program Studi Sarjana Farmasi',
            'tahun' => '2024',
            'status' => 'berlaku',
            'tanggal_berlaku' => '2024-08-01',
        ]);
    }

    /**
     * @return array{0:array<string,ProfilLulusan>,1:array<string,Cpl>}
     */
    private function seedProfilDanCpl(Kurikulum $kurikulum): array
    {
        $profilData = [
            'PL1' => 'Ilmuwan farmasi yang menguasai konsep sains kefarmasian secara komprehensif.',
            'PL2' => 'Pengkaji obat yang mampu menganalisis mekanisme dan penggunaan obat secara rasional.',
            'PL3' => 'Komunikator yang mampu menyampaikan informasi kefarmasian secara ilmiah dan etis.',
            'PL4' => 'Pembelajar sepanjang hayat yang adaptif terhadap perkembangan ilmu kefarmasian.',
        ];
        $profil = [];
        foreach ($profilData as $kode => $deskripsi) {
            $profil[$kode] = ProfilLulusan::create([
                'institusi_id' => $this->institusiId,
                'kurikulum_id' => $kurikulum->id,
                'kode' => $kode,
                'deskripsi' => $deskripsi,
            ]);
        }

        $cplData = [
            ['CPL01', 'sikap', 'Menjunjung tinggi nilai kemanusiaan dan etika profesi kefarmasian dalam menjalankan tugas.'],
            ['CPL02', 'sikap', 'Menunjukkan sikap bertanggung jawab atas pekerjaan secara mandiri maupun dalam tim.'],
            ['CPL03', 'pengetahuan', 'Menguasai konsep teoretis farmakologi meliputi farmakokinetika dan farmakodinamika obat.'],
            ['CPL04', 'pengetahuan', 'Menguasai prinsip mekanisme kerja obat, kemoterapi, dan resistensi antimikroba.'],
            ['CPL05', 'keterampilan_umum', 'Mampu menerapkan pemikiran logis, kritis, dan sistematis dalam mengkaji ilmu kefarmasian.'],
            ['CPL06', 'keterampilan_umum', 'Mampu melakukan pembelajaran mandiri dan mengelola informasi ilmiah secara bertanggung jawab.'],
            ['CPL07', 'keterampilan_khusus', 'Mampu menganalisis efek dan mekanisme kerja obat pada berbagai sistem organ tubuh.'],
            ['CPL08', 'keterampilan_khusus', 'Mampu mengevaluasi penggunaan obat yang rasional beserta risiko efek sampingnya.'],
        ];
        $cpl = [];
        foreach ($cplData as [$kode, $aspek, $deskripsi]) {
            $cpl[$kode] = Cpl::create([
                'institusi_id' => $this->institusiId,
                'kurikulum_id' => $kurikulum->id,
                'kode' => $kode,
                'deskripsi' => $deskripsi,
                'aspek' => $aspek,
                'level_kkni' => '6',
                'sumber' => 'SN-Dikti',
            ]);
            TargetCpl::create([
                'institusi_id' => $this->institusiId,
                'cpl_id' => $cpl[$kode]->id,
                'angkatan' => '2024',
                'ambang_nilai' => 60.00,
                'persentase_target' => 75.00,
            ]);
        }

        $peta = [
            'PL1' => ['CPL01', 'CPL03', 'CPL04'],
            'PL2' => ['CPL03', 'CPL04', 'CPL07', 'CPL08'],
            'PL3' => ['CPL02', 'CPL05'],
            'PL4' => ['CPL05', 'CPL06'],
        ];
        foreach ($peta as $plKode => $cplKodes) {
            foreach ($cplKodes as $cplKode) {
                PlCpl::create([
                    'institusi_id' => $this->institusiId,
                    'profil_lulusan_id' => $profil[$plKode]->id,
                    'cpl_id' => $cpl[$cplKode]->id,
                ]);
            }
        }

        return [$profil, $cpl];
    }

    /**
     * @param  array<string,Cpl>  $cpl
     * @return array<string,BahanKajian>
     */
    private function seedBahanKajian(Kurikulum $kurikulum, array $cpl): array
    {
        $bkData = [
            'BK1' => ['Prinsip Dasar Farmakologi', 'Farmakokinetika dan farmakodinamika obat.', ['CPL03']],
            'BK2' => ['Farmakologi Sistem Organ', 'Kerja obat pada sistem saraf, kardiovaskular, dan organ lain.', ['CPL03', 'CPL07']],
            'BK3' => ['Kemoterapi dan Antimikroba', 'Antibiotik, antivirus, antijamur, dan resistensi.', ['CPL04', 'CPL07']],
            'BK4' => ['Toksikologi Dasar', 'Efek toksik obat dan bahan kimia terhadap tubuh.', ['CPL04']],
            'BK5' => ['Penggunaan Obat Rasional', 'Kajian efektivitas, keamanan, dan ketepatan penggunaan obat.', ['CPL08']],
        ];
        $bahanKajian = [];
        foreach ($bkData as $kode => [$nama, $deskripsi, $cplKodes]) {
            $bk = BahanKajian::create([
                'institusi_id' => $this->institusiId,
                'kurikulum_id' => $kurikulum->id,
                'nama' => $nama,
                'deskripsi' => $deskripsi,
            ]);
            $bahanKajian[$kode] = $bk;

            Keterampilan::create([
                'institusi_id' => $this->institusiId,
                'bahan_kajian_id' => $bk->id,
                'deskripsi' => 'Menganalisis ' . strtolower($nama) . ' secara sistematis.',
                'domain' => 'kognitif',
                'tingkat_kemampuan' => 4,
                'sumber' => 'prodi',
            ]);

            foreach ($cplKodes as $cplKode) {
                CplBahanKajian::create([
                    'institusi_id' => $this->institusiId,
                    'cpl_id' => $cpl[$cplKode]->id,
                    'bahan_kajian_id' => $bk->id,
                ]);
            }
        }

        return $bahanKajian;
    }

    /**
     * @param  array<string,Cpl>  $cpl
     * @param  array<string,BahanKajian>  $bahanKajian
     */
    private function seedMataKuliah(Kurikulum $kurikulum, array $cpl, array $bahanKajian): void
    {
        MataKuliah::create([
            'institusi_id' => $this->institusiId,
            'kurikulum_id' => $kurikulum->id,
            'kode_mk' => $this->kodeMk,
            'nama' => 'Farmakologi Dasar',
            'jenis_mk' => 'murni',
            'sifat' => 'wajib',
            'rumpun' => 'Farmakologi dan Farmasi Klinik',
            'deskripsi_singkat' => 'Mata kuliah ini membahas prinsip dasar farmakologi meliputi farmakokinetika, '
                . 'farmakodinamika, kerja obat pada sistem organ, kemoterapi antimikroba, serta prinsip '
                . 'penggunaan obat yang rasional sebagai landasan farmasi klinik.',
            'sks_teori' => 3,
            'sks_praktik' => 0,
            'semester' => 3,
            'prodi_kode' => '48201-PSSF',
            'prasyarat_kode' => null,
        ]);

        $mkCpl = ['CPL03' => 30, 'CPL04' => 25, 'CPL05' => 15, 'CPL07' => 20, 'CPL08' => 10];
        foreach ($mkCpl as $cplKode => $bobot) {
            MkCpl::create([
                'institusi_id' => $this->institusiId,
                'kode_mk' => $this->kodeMk,
                'cpl_id' => $cpl[$cplKode]->id,
                'bobot' => $bobot,
            ]);
        }

        foreach (['BK1', 'BK2', 'BK3', 'BK5'] as $bkKode) {
            MkBahanKajian::create([
                'institusi_id' => $this->institusiId,
                'kode_mk' => $this->kodeMk,
                'bahan_kajian_id' => $bahanKajian[$bkKode]->id,
            ]);
            foreach ($bahanKajian[$bkKode]->keterampilan as $keterampilan) {
                MkKeterampilan::create([
                    'institusi_id' => $this->institusiId,
                    'kode_mk' => $this->kodeMk,
                    'keterampilan_id' => $keterampilan->id,
                ]);
            }
        }

        $dosen = Dosen::create([
            'institusi_id' => $this->institusiId,
            'nidn' => '0912345678',
            'nama' => 'Dr. apt. Uji Dosen, M.Farm.',
        ]);
        $anggota = Dosen::create([
            'institusi_id' => $this->institusiId,
            'nidn' => '0923456789',
            'nama' => 'apt. Sari Wijaya, M.Sc.',
        ]);

        MkPengampu::create([
            'institusi_id' => $this->institusiId,
            'kode_mk' => $this->kodeMk,
            'dosen_nidn' => $dosen->nidn,
            'peran' => 'koordinator',
        ]);
        MkPengampu::create([
            'institusi_id' => $this->institusiId,
            'kode_mk' => $this->kodeMk,
            'dosen_nidn' => $anggota->nidn,
            'peran' => 'anggota',
        ]);
    }

    /**
     * @param  array<string,Cpl>  $cpl
     * @return array{0:array<string,Cpmk>,1:array<string,SubCpmk>}
     */
    private function seedCpmk(array $cpl): array
    {
        $taksonomiId = fn(string $kode): ?int => Taksonomi::whereNull('institusi_id')
            ->where('kode', $kode)->value('id');

        // kode CPMK => [deskripsi, bobot ke nilai MK, taksonomi, [cplKode => bobot ke CPL]]
        $cpmkData = [
            'CPMK1' => [
                'Mampu menjelaskan prinsip dasar farmakologi meliputi farmakokinetika dan farmakodinamika obat.',
                15,
                'C2',
                ['CPL03' => 100],
            ],
            'CPMK2' => [
                'Mampu menganalisis mekanisme kerja obat pada berbagai sistem organ tubuh.',
                25,
                'C4',
                ['CPL03' => 40, 'CPL07' => 60],
            ],
            'CPMK3' => [
                'Mampu menganalisis prinsip kemoterapi antimikroba beserta mekanisme resistensinya.',
                25,
                'C4',
                ['CPL04' => 50, 'CPL07' => 50],
            ],
            'CPMK4' => [
                'Mampu mengevaluasi penggunaan obat yang rasional beserta risiko efek sampingnya.',
                20,
                'C5',
                ['CPL08' => 100],
            ],
            'CPMK5' => [
                'Mampu bekerja sama dan mengomunikasikan hasil kajian farmakologi secara ilmiah dan bertanggung jawab.',
                15,
                'A3',
                ['CPL02' => 40, 'CPL05' => 60],
            ],
        ];

        $cpmk = [];
        foreach ($cpmkData as $kode => [$deskripsi, $bobotMk, $takKode, $cplBobot]) {
            $c = Cpmk::create([
                'institusi_id' => $this->institusiId,
                'kode_mk' => $this->kodeMk,
                'kode' => $kode,
                'deskripsi' => $deskripsi,
                'bobot_persen' => $bobotMk,
                'taksonomi_id' => $taksonomiId($takKode),
                'taksonomi_kode' => [$takKode],
            ]);
            $cpmk[$kode] = $c;

            foreach ($cplBobot as $cplKode => $bobot) {
                CpmkCpl::create([
                    'institusi_id' => $this->institusiId,
                    'cpmk_id' => $c->id,
                    'cpl_id' => $cpl[$cplKode]->id,
                    'bobot' => $bobot,
                ]);
            }
        }

        // kode Sub => [cpmk induk, deskripsi, minggu, bobot penilaian, taksonomi, [indikator...]]
        $subData = [
            'Sub-CPMK1.1' => [
                'CPMK1',
                'Mampu menjelaskan ruang lingkup dan peran farmakologi dalam kefarmasian.',
                1,
                6,
                'C2',
                ['Ketepatan menjelaskan definisi dan ruang lingkup farmakologi.']
            ],
            'Sub-CPMK1.2' => [
                'CPMK1',
                'Mampu menjelaskan proses farmakokinetika (absorpsi, distribusi, metabolisme, ekskresi).',
                2,
                6,
                'C2',
                ['Ketepatan menjelaskan tahapan ADME obat.']
            ],
            'Sub-CPMK1.3' => [
                'CPMK1',
                'Mampu menjelaskan konsep farmakodinamika dan interaksi obat-reseptor.',
                3,
                6,
                'C2',
                ['Ketepatan menjelaskan hubungan dosis-respons dan mekanisme reseptor.']
            ],
            'Sub-CPMK2.1' => [
                'CPMK2',
                'Mampu menganalisis kerja obat pada sistem saraf otonom.',
                4,
                6,
                'C4',
                ['Ketepatan menganalisis efek agonis dan antagonis otonom.']
            ],
            'Sub-CPMK2.2' => [
                'CPMK2',
                'Mampu menganalisis kerja obat pada sistem kardiovaskular.',
                5,
                6,
                'C4',
                ['Ketepatan menganalisis mekanisme obat kardiovaskular.']
            ],
            'Sub-CPMK2.3' => [
                'CPMK2',
                'Mampu menganalisis kerja obat pada sistem saraf pusat.',
                6,
                6,
                'C4',
                ['Ketepatan menganalisis mekanisme obat sistem saraf pusat.']
            ],
            'Sub-CPMK2.4' => [
                'CPMK2',
                'Mampu menganalisis kerja obat pada sistem pencernaan dan endokrin.',
                7,
                7,
                'C4',
                ['Ketepatan menganalisis mekanisme obat pencernaan dan endokrin.']
            ],
            'Sub-CPMK3.1' => [
                'CPMK3',
                'Mampu menganalisis prinsip antimikroba dan antibiotik beta-laktam.',
                9,
                7,
                'C4',
                ['Ketepatan menganalisis mekanisme kerja antibiotik beta-laktam.']
            ],
            'Sub-CPMK3.2' => [
                'CPMK3',
                'Mampu menganalisis golongan antibiotik lain dan mekanisme resistensi.',
                10,
                7,
                'C4',
                ['Ketepatan menganalisis mekanisme resistensi antimikroba.']
            ],
            'Sub-CPMK3.3' => [
                'CPMK3',
                'Mampu menganalisis obat antivirus, antijamur, dan antiparasit.',
                11,
                7,
                'C4',
                ['Ketepatan menganalisis mekanisme antivirus, antijamur, dan antiparasit.']
            ],
            'Sub-CPMK4.1' => [
                'CPMK4',
                'Mampu mengevaluasi efek samping dan interaksi obat.',
                12,
                8,
                'C5',
                ['Ketepatan mengevaluasi risiko efek samping dan interaksi obat.']
            ],
            'Sub-CPMK4.2' => [
                'CPMK4',
                'Mampu mengevaluasi ketepatan penggunaan obat yang rasional.',
                13,
                8,
                'C5',
                ['Ketepatan mengevaluasi indikator penggunaan obat rasional.']
            ],
            'Sub-CPMK5.1' => [
                'CPMK5',
                'Mampu menyusun dan mempresentasikan kajian farmakologi terapan.',
                14,
                10,
                'A3',
                ['Kualitas penyajian dan penguasaan materi kajian.']
            ],
            'Sub-CPMK5.2' => [
                'CPMK5',
                'Mampu berdiskusi dan menelaah pustaka farmakologi secara kritis.',
                15,
                10,
                'A3',
                ['Kualitas argumentasi dan telaah pustaka.']
            ],
        ];

        $subCpmk = [];
        foreach ($subData as $kode => [$cpmkKode, $deskripsi, $minggu, $bobot, $takKode, $indikatorList]) {
            $s = SubCpmk::create([
                'institusi_id' => $this->institusiId,
                'cpmk_id' => $cpmk[$cpmkKode]->id,
                'kode' => $kode,
                'deskripsi' => $deskripsi,
                'minggu_mulai' => $minggu,
                'minggu_selesai' => $minggu,
                'bobot_persen' => $bobot,
                'taksonomi_id' => $taksonomiId($takKode),
                'taksonomi_kode' => [$takKode],
            ]);
            $subCpmk[$kode] = $s;

            foreach ($indikatorList as $deskripsiIndikator) {
                Indikator::create([
                    'institusi_id' => $this->institusiId,
                    'sub_cpmk_id' => $s->id,
                    'deskripsi' => $deskripsiIndikator,
                ]);
            }
        }

        return [$cpmk, $subCpmk];
    }

    private function seedReferensi(): void
    {
        $referensi = [
            ['utama', 'Katzung BG, Vanderah TW. Basic & Clinical Pharmacology. 15th ed. New York: McGraw-Hill Education; 2021.'],
            ['utama', "Brunton LL, Knollmann BC. Goodman & Gilman's The Pharmacological Basis of Therapeutics. 14th ed. New York: McGraw-Hill; 2023."],
            ['utama', 'Departemen Farmakologi dan Terapeutik FKUI. Farmakologi dan Terapi. Edisi 6. Jakarta: Badan Penerbit FKUI; 2016.'],
            ['pendukung', "Ritter JM, Flower RJ, Henderson G, dkk. Rang and Dale's Pharmacology. 9th ed. Edinburgh: Elsevier; 2020."],
            ['pendukung', 'Ikatan Apoteker Indonesia. ISO Farmakoterapi. Jakarta: PT ISFI Penerbitan; 2020.'],
        ];
        foreach ($referensi as [$tipe, $sitasi]) {
            Referensi::create([
                'institusi_id' => $this->institusiId,
                'kode_mk' => $this->kodeMk,
                'tipe' => $tipe,
                'sitasi' => $sitasi,
            ]);
        }
    }

    /**
     * @param  array<string,SubCpmk>  $subCpmk
     */
    private function seedRps(\App\Models\VersiPedoman $versiPedoman, array $subCpmk): void
    {
        $rps = RpsVersion::create([
            'institusi_id' => $this->institusiId,
            'kode_mk' => $this->kodeMk,
            'versi' => 1,
            'status' => 'approved',
            'bahasa' => 'id',
            'versi_pedoman_id' => $versiPedoman->id,
            'created_by' => 2,
            'koordinator_mk' => 2,
            'approved_by' => 3,
            'tanggal_penyusunan' => Carbon::create(2024, 7, 15),
            'kode_dokumen' => 'RPS/FF/FAR201/2024',
            'submitted_at' => Carbon::create(2024, 7, 20, 9),
            'approved_at' => Carbon::create(2024, 7, 25, 10),
            'catatan_review' => 'RPS telah ditinjau dan disetujui oleh Ketua Program Studi.',
        ]);

        $estimasi = app(EstimasiWaktuService::class)
            ->hitung(3, 0, app(EstimasiWaktuService::class)->konversiUntuk($this->institusiId));

        $topik = [
            1 => 'Pengantar farmakologi dan ruang lingkupnya',
            2 => 'Farmakokinetika: absorpsi, distribusi, metabolisme, dan ekskresi',
            3 => 'Farmakodinamika: reseptor dan hubungan dosis-respons',
            4 => 'Farmakologi sistem saraf otonom',
            5 => 'Farmakologi sistem kardiovaskular',
            6 => 'Farmakologi sistem saraf pusat',
            7 => 'Farmakologi sistem pencernaan dan endokrin',
            8 => 'Ujian Tengah Semester (UTS)',
            9 => 'Antimikroba: prinsip dan antibiotik beta-laktam',
            10 => 'Antibiotik golongan lain dan resistensi antimikroba',
            11 => 'Antivirus, antijamur, dan antiparasit',
            12 => 'Efek samping obat dan interaksi obat',
            13 => 'Prinsip penggunaan obat yang rasional',
            14 => 'Presentasi kajian farmakologi terapan',
            15 => 'Diskusi kasus dan telaah pustaka',
            16 => 'Ujian Akhir Semester (UAS)',
        ];

        // minggu => kode Sub-CPMK
        $subPerMinggu = [
            1 => 'Sub-CPMK1.1',
            2 => 'Sub-CPMK1.2',
            3 => 'Sub-CPMK1.3',
            4 => 'Sub-CPMK2.1',
            5 => 'Sub-CPMK2.2',
            6 => 'Sub-CPMK2.3',
            7 => 'Sub-CPMK2.4',
            9 => 'Sub-CPMK3.1',
            10 => 'Sub-CPMK3.2',
            11 => 'Sub-CPMK3.3',
            12 => 'Sub-CPMK4.1',
            13 => 'Sub-CPMK4.2',
            14 => 'Sub-CPMK5.1',
            15 => 'Sub-CPMK5.2',
        ];

        // minggu => bobot penilaian mingguan (total 100)
        $bobotMinggu = [3 => 5, 5 => 8, 6 => 5, 8 => 20, 10 => 8, 11 => 5, 13 => 9, 14 => 8, 15 => 7, 16 => 25];

        foreach ($topik as $minggu => $judul) {
            $sub = $subPerMinggu[$minggu] ?? null;
            $isUjian = in_array($minggu, [8, 16], true);

            RpsMinggu::create([
                'rps_version_id' => $rps->id,
                'minggu_ke' => $minggu,
                'sub_cpmk_id' => $sub ? $subCpmk[$sub]->id : null,
                'indikator' => $isUjian
                    ? 'Ketepatan menjawab soal ujian sesuai capaian yang diujikan.'
                    : 'Ketepatan penjelasan dan analisis terkait ' . lcfirst($judul) . '.',
                'teknik_kriteria_penilaian' => $isUjian
                    ? 'Ujian tertulis; penilaian mengacu pada kunci jawaban dan rubrik.'
                    : 'Partisipasi, tanya jawab, dan penilaian tugas dengan rubrik.',
                'metode_pembelajaran' => $isUjian
                    ? 'Ujian tertulis (closed book)'
                    : 'Kuliah interaktif, diskusi kelompok, dan studi kasus',
                'bentuk_luring' => $isUjian ? 'Ujian tatap muka di kelas' : 'Kuliah tatap muka di kelas',
                'bentuk_daring' => $isUjian ? null : 'LMS: materi, kuis, dan forum diskusi asinkron',
                'materi_pustaka' => $isUjian
                    ? $judul . ' — materi minggu terkait; Pustaka [1], [2], [3].'
                    : $judul . '. Pustaka [1], [2], [3].',
                'pengalaman_belajar' => $isUjian
                    ? 'Mahasiswa mengerjakan soal ujian secara mandiri.'
                    : 'Mahasiswa mengkaji materi ' . lcfirst($judul) . ' dan mengerjakan latihan/tugas terstruktur.',
                'estimasi_waktu' => $estimasi,
                'bobot_penilaian' => $bobotMinggu[$minggu] ?? null,
            ]);
        }

        $this->seedPenilaian($rps, $subCpmk);
    }

    /**
     * @param  array<string,SubCpmk>  $subCpmk
     */
    private function seedPenilaian(RpsVersion $rps, array $subCpmk): void
    {
        // nama, jenis, bobot, minggu, sub-cpmk terkait
        $komponen = [
            ['Kuis', 'kuis', 15, 3, 'Sub-CPMK1.3'],
            ['Tugas Terstruktur', 'tugas', 25, 5, 'Sub-CPMK2.2'],
            ['Presentasi Kajian', 'skill_assessment', 15, 14, 'Sub-CPMK5.1'],
            ['Ujian Tengah Semester', 'uts', 20, 8, null],
            ['Ujian Akhir Semester', 'uas', 25, 16, null],
        ];

        $presentasi = null;
        foreach ($komponen as [$nama, $jenis, $bobot, $minggu, $subKode]) {
            $k = KomponenPenilaian::create([
                'rps_version_id' => $rps->id,
                'sub_cpmk_id' => $subKode ? $subCpmk[$subKode]->id : null,
                'nama' => $nama,
                'jenis' => $jenis,
                'instrumen' => 'Soal/rubrik penilaian ' . strtolower($nama) . '.',
                'bobot_persen' => $bobot,
                'minggu_ke' => $minggu,
            ]);
            if ($jenis === 'skill_assessment') {
                $presentasi = $k;
            }
        }

        if ($presentasi) {
            $rubrik = Rubrik::create([
                'komponen_penilaian_id' => $presentasi->id,
                'jenis' => 'analitik',
                'jumlah_level_skala' => 4,
                'label_skala' => ['Kurang', 'Cukup', 'Baik', 'Sangat Baik'],
            ]);

            $kriteria = [
                ['Penguasaan Materi', 40, [
                    'Materi kurang dikuasai dan banyak keliru.',
                    'Materi cukup dikuasai dengan sedikit kekeliruan.',
                    'Materi dikuasai dengan baik dan akurat.',
                    'Materi dikuasai sangat baik, akurat, dan mendalam.',
                ]],
                ['Sistematika Penyajian', 30, [
                    'Penyajian tidak terstruktur.',
                    'Penyajian cukup terstruktur.',
                    'Penyajian terstruktur dan runtut.',
                    'Penyajian sangat terstruktur, runtut, dan menarik.',
                ]],
                ['Kemampuan Diskusi', 30, [
                    'Sulit menjawab pertanyaan.',
                    'Menjawab sebagian pertanyaan.',
                    'Menjawab pertanyaan dengan tepat.',
                    'Menjawab pertanyaan dengan tepat dan argumentatif.',
                ]],
            ];
            foreach ($kriteria as $i => [$namaKriteria, $bobot, $deskriptor]) {
                RubrikKriteria::create([
                    'rubrik_id' => $rubrik->id,
                    'kriteria' => $namaKriteria,
                    'bobot' => $bobot,
                    'deskriptor' => $deskriptor,
                    'urutan' => $i + 1,
                ]);
            }
        }
    }
}
