<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarding, dokumen rujukan, dan KERANGKA ACUAN GENERIK (checklist).
 * Kerangka acuan lintas otoritas (KPT/SN-Dikti, APTFI, LAM-PTKes, BAN-PT, dll)
 * berisi butir yang bisa diceklist terhadap kurikulum yang di-upload.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badan_rujukan', function (Blueprint $table) {
            $table->id();
            // null = global (dibagikan ke semua tenant), mis. KPT/APTFI/LAM nasional
            $table->foreignId('institusi_id')->nullable()->constrained('institusi')->cascadeOnDelete();
            $table->string('nama'); // APTFI / LAM-PTKes / Kemdiktisaintek / BAN-PT
            $table->string('jenis'); // asosiasi/akreditasi/pemerintah/institusi
            $table->string('disiplin')->nullable(); // Farmasi/Teknik/...
            $table->timestamps();
        });

        Schema::create('dokumen_rujukan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->foreignId('badan_rujukan_id')->nullable()->constrained('badan_rujukan')->nullOnDelete();
            $table->string('jenis'); // kpt/asosiasi/akreditasi/template_rps
            $table->string('judul')->nullable();
            $table->string('file_asal')->nullable();
            $table->string('file_path')->nullable();
            $table->string('status_indexing')->default('pending');
            $table->string('vector_namespace')->nullable();
            $table->timestamps();
        });

        Schema::create('versi_pedoman', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dokumen_id')->constrained('dokumen_rujukan')->cascadeOnDelete();
            $table->string('versi');
            $table->date('tanggal_berlaku')->nullable();
            $table->date('tanggal_nonaktif')->nullable();
            $table->json('mk_terdampak')->nullable();
            $table->timestamps();
        });

        Schema::create('kerangka_acuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('badan_rujukan_id')->constrained('badan_rujukan')->cascadeOnDelete();
            $table->foreignId('dokumen_id')->nullable()->constrained('dokumen_rujukan')->nullOnDelete();
            $table->string('nama'); // SKAI 2023 / KPT 2020 / Instrumen LAM-PTKes
            $table->string('versi')->nullable();
            $table->date('tanggal_berlaku')->nullable();
            $table->timestamps();
        });

        Schema::create('butir_acuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kerangka_acuan_id')->constrained('kerangka_acuan')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('butir_acuan')->nullOnDelete();
            $table->string('kategori'); // profil_lulusan/cpl/bahan_kajian/kriteria_akreditasi/struktur/aturan
            $table->string('kode')->nullable();
            $table->text('deskripsi');
            $table->boolean('wajib')->default(true);
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('template_rps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->foreignId('dokumen_asal_id')->nullable()->constrained('dokumen_rujukan')->nullOnDelete();
            $table->string('nama')->nullable();
            $table->json('struktur_kolom');
            $table->unsignedBigInteger('dikonfirmasi_oleh')->nullable();
            $table->timestamps();
        });

        Schema::create('konfigurasi_aturan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->foreignId('badan_rujukan_id')->nullable()->constrained('badan_rujukan')->nullOnDelete();
            $table->string('jenis_aturan'); // jumlah_minggu/bobot_total/dst
            $table->json('nilai');
            $table->unsignedBigInteger('diisi_oleh')->nullable();
            $table->foreignId('referensi_dokumen_id')->nullable()->constrained('dokumen_rujukan')->nullOnDelete();
            $table->unsignedInteger('referensi_halaman')->nullable();
            $table->timestamps();
        });

        Schema::create('column_mapping', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->string('jenis_file'); // cpl/bahan_kajian/mata_kuliah
            $table->json('mapping');
            $table->timestamps();
        });

        Schema::create('dokumen_chunk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dokumen_id')->constrained('dokumen_rujukan')->cascadeOnDelete();
            $table->unsignedInteger('urutan')->default(0);
            $table->longText('teks');
            $table->unsignedInteger('halaman')->nullable();
            $table->json('embedding')->nullable(); // vektor (JSON, cosine di app)
            $table->unsignedInteger('token_count')->nullable();
            $table->timestamps();
            $table->index('dokumen_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_chunk');
        Schema::dropIfExists('column_mapping');
        Schema::dropIfExists('konfigurasi_aturan');
        Schema::dropIfExists('template_rps');
        Schema::dropIfExists('butir_acuan');
        Schema::dropIfExists('kerangka_acuan');
        Schema::dropIfExists('versi_pedoman');
        Schema::dropIfExists('dokumen_rujukan');
        Schema::dropIfExists('badan_rujukan');
    }
};
