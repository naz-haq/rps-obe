<?php

namespace Database\Seeders;

use App\Models\Taksonomi;
use Illuminate\Database\Seeder;

/**
 * Seed taksonomi GLOBAL (institusi_id null) — bawaan sistem:
 * - Kognitif  : Bloom (revisi Anderson & Krathwohl) C1..C6
 * - Afektif   : Krathwohl A1..A5
 * - Psikomotor: Dave P1..P5
 * Idempotent via updateOrCreate (kunci: institusi_id null + kerangka + kode).
 */
class TaksonomiSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            // --- Kognitif (Bloom-Anderson) ---
            ['kognitif', 'bloom_anderson', 'C1', 'Mengingat', 1, 'Menarik kembali pengetahuan dari memori.', ['mendefinisikan', 'menyebutkan', 'mengidentifikasi', 'menuliskan', 'menyatakan']],
            ['kognitif', 'bloom_anderson', 'C2', 'Memahami', 2, 'Membangun makna dari informasi.', ['menjelaskan', 'menguraikan', 'merangkum', 'mencontohkan', 'mengklasifikasikan']],
            ['kognitif', 'bloom_anderson', 'C3', 'Menerapkan', 3, 'Menggunakan prosedur pada situasi tertentu.', ['menerapkan', 'menghitung', 'mendemonstrasikan', 'menggunakan', 'menyelesaikan']],
            ['kognitif', 'bloom_anderson', 'C4', 'Menganalisis', 4, 'Menguraikan menjadi bagian dan hubungannya.', ['menganalisis', 'membedakan', 'mengorganisasi', 'membandingkan', 'menelaah']],
            ['kognitif', 'bloom_anderson', 'C5', 'Mengevaluasi', 5, 'Membuat penilaian berdasarkan kriteria.', ['mengevaluasi', 'menilai', 'mengkritik', 'memutuskan', 'merekomendasikan']],
            ['kognitif', 'bloom_anderson', 'C6', 'Mencipta', 6, 'Menyusun elemen menjadi kesatuan/produk baru.', ['merancang', 'menyusun', 'mengembangkan', 'memformulasikan', 'menciptakan']],

            // --- Afektif (Krathwohl) ---
            ['afektif', 'krathwohl', 'A1', 'Menerima', 1, 'Kesediaan memperhatikan fenomena/stimulus.', ['menanyakan', 'mengikuti', 'memilih', 'mematuhi']],
            ['afektif', 'krathwohl', 'A2', 'Merespons', 2, 'Partisipasi aktif dan reaksi terhadap stimulus.', ['menjawab', 'membantu', 'mempresentasikan', 'melaksanakan']],
            ['afektif', 'krathwohl', 'A3', 'Menghargai', 3, 'Memberi nilai pada objek/perilaku.', ['menghargai', 'mendukung', 'meyakinkan', 'menginisiasi']],
            ['afektif', 'krathwohl', 'A4', 'Mengorganisasi', 4, 'Memadukan nilai menjadi sistem nilai.', ['mengorganisasi', 'membandingkan', 'memadukan', 'merumuskan']],
            ['afektif', 'krathwohl', 'A5', 'Karakterisasi', 5, 'Menjadikan nilai sebagai karakter/pola hidup.', ['menunjukkan', 'membiasakan', 'mempertahankan', 'membuktikan']],

            // --- Psikomotorik (Dave) ---
            ['psikomotorik', 'dave', 'P1', 'Imitasi', 1, 'Meniru gerakan setelah mengamati.', ['meniru', 'mengikuti', 'mengulangi', 'mencoba']],
            ['psikomotorik', 'dave', 'P2', 'Manipulasi', 2, 'Melakukan gerakan berdasar instruksi.', ['melakukan', 'melaksanakan', 'mengoperasikan', 'membuat']],
            ['psikomotorik', 'dave', 'P3', 'Presisi', 3, 'Melakukan gerakan dengan akurat & mandiri.', ['menunjukkan', 'mengkalibrasi', 'mengendalikan', 'menyempurnakan']],
            ['psikomotorik', 'dave', 'P4', 'Artikulasi', 4, 'Mengoordinasikan serangkaian gerakan selaras.', ['mengoordinasikan', 'mengintegrasikan', 'menyesuaikan', 'merangkai']],
            ['psikomotorik', 'dave', 'P5', 'Naturalisasi', 5, 'Melakukan gerakan otomatis & alami.', ['mendesain', 'menciptakan', 'mengelola', 'membiasakan']],
        ];

        foreach ($data as [$domain, $kerangka, $kode, $nama, $level, $deskripsi, $kataKerja]) {
            Taksonomi::updateOrCreate(
                ['institusi_id' => null, 'kerangka' => $kerangka, 'kode' => $kode],
                [
                    'domain'     => $domain,
                    'nama'       => $nama,
                    'level'      => $level,
                    'deskripsi'  => $deskripsi,
                    'kata_kerja' => $kataKerja,
                ]
            );
        }
    }
}
