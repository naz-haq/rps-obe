<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CPMK, CPMK×CPL, Sub-CPMK, Indikator, dan RPS (versi + rincian mingguan) + Referensi.
 * CPMK/RPS merujuk MK lewat kode_mk (kunci natural).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpmk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->string('kode_mk');
            $table->string('kode');
            $table->text('deskripsi');
            $table->decimal('bobot_persen', 6, 2)->nullable(); // kontribusi ke nilai MK
            $table->timestamps();
            $table->index(['institusi_id', 'kode_mk']);
        });

        Schema::create('cpmk_cpl', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->foreignId('cpmk_id')->constrained('cpmk')->cascadeOnDelete();
            $table->foreignId('cpl_id')->constrained('cpl')->cascadeOnDelete();
            $table->decimal('bobot', 6, 2)->nullable(); // kontribusi CPMK ke CPL
            $table->timestamps();
            $table->unique(['cpmk_id', 'cpl_id']);
        });

        Schema::create('sub_cpmk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->foreignId('cpmk_id')->constrained('cpmk')->cascadeOnDelete();
            $table->string('kode');
            $table->text('deskripsi');
            $table->unsignedTinyInteger('minggu_mulai')->nullable();
            $table->unsignedTinyInteger('minggu_selesai')->nullable();
            $table->decimal('bobot_persen', 6, 2)->nullable();
            $table->foreignId('taksonomi_id')->nullable()->constrained('taksonomi')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('indikator', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->foreignId('sub_cpmk_id')->constrained('sub_cpmk')->cascadeOnDelete();
            $table->text('deskripsi');
            $table->timestamps();
        });

        Schema::create('rps_version', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique(); // kunci tukar lintas-service (dikonsumsi LMS)
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->string('kode_mk');
            $table->unsignedInteger('versi')->default(1);
            $table->string('status')->default('draft'); // draft/review/revisi/approved
            $table->string('bahasa')->default('id');    // id/en
            $table->foreignId('versi_pedoman_id')->nullable()->constrained('versi_pedoman')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();     // dosen pengembang
            $table->unsignedBigInteger('koordinator_mk')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();    // ketua prodi
            $table->date('tanggal_penyusunan')->nullable();
            $table->timestamps();
            $table->index(['institusi_id', 'kode_mk']);
        });

        Schema::create('rps_minggu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rps_version_id')->constrained('rps_version')->cascadeOnDelete();
            $table->unsignedTinyInteger('minggu_ke');
            $table->foreignId('sub_cpmk_id')->nullable()->constrained('sub_cpmk')->nullOnDelete();
            $table->text('indikator')->nullable();
            $table->text('teknik_kriteria_penilaian')->nullable();
            $table->text('metode_pembelajaran')->nullable(); // kuliah/diskusi/PBL/demonstrasi/praktik
            $table->text('bentuk_luring')->nullable();
            $table->text('bentuk_daring')->nullable();
            $table->text('materi_pustaka')->nullable();
            $table->text('pengalaman_belajar')->nullable(); // penugasan mahasiswa
            $table->json('estimasi_waktu')->nullable(); // TM/PT/KM atau praktik (menit)
            $table->decimal('bobot_penilaian', 6, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('komponen_penilaian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rps_version_id')->constrained('rps_version')->cascadeOnDelete();
            $table->foreignId('sub_cpmk_id')->nullable()->constrained('sub_cpmk')->nullOnDelete();
            $table->string('nama'); // Tugas-1/Kuis/UTS/UAS/Laporan Praktikum/OSCE
            $table->string('jenis'); // tugas/kuis/uts/uas/laporan_praktikum/osce/skill_assessment/responsi
            $table->text('instrumen')->nullable();
            $table->decimal('bobot_persen', 6, 2)->nullable();
            $table->unsignedTinyInteger('minggu_ke')->nullable();
            $table->timestamps();
        });

        Schema::create('rubrik', function (Blueprint $table) {
            $table->id();
            $table->foreignId('komponen_penilaian_id')->constrained('komponen_penilaian')->cascadeOnDelete();
            $table->string('jenis')->default('analitik'); // analitik/holistik/checklist_skill/lembar_observasi
            $table->unsignedTinyInteger('jumlah_level_skala')->default(4);
            $table->json('label_skala')->nullable(); // [Kurang, Cukup, Baik, Sangat Baik]
            $table->timestamps();
        });

        Schema::create('rubrik_kriteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubrik_id')->constrained('rubrik')->cascadeOnDelete();
            $table->string('kriteria');
            $table->decimal('bobot', 6, 2)->nullable();
            $table->json('deskriptor')->nullable(); // deskripsi per level skala
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('referensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->string('kode_mk');
            $table->string('tipe')->nullable(); // utama/pendukung
            $table->text('sitasi');
            $table->timestamps();
            $table->index(['institusi_id', 'kode_mk']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rubrik_kriteria');
        Schema::dropIfExists('rubrik');
        Schema::dropIfExists('komponen_penilaian');
        Schema::dropIfExists('referensi');
        Schema::dropIfExists('rps_minggu');
        Schema::dropIfExists('rps_version');
        Schema::dropIfExists('indikator');
        Schema::dropIfExists('sub_cpmk');
        Schema::dropIfExists('cpmk_cpl');
        Schema::dropIfExists('cpmk');
    }
};
