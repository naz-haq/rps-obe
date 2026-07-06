<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master data lokal (Tahap 1 standalone) + payung KURIKULUM.
 * Di Tahap 2 (ekosistem) institusi/dosen/mata_kuliah ditarik dari Academic Core;
 * entitas OBE merujuk MK/dosen lewat KUNCI NATURAL (kode_mk / nidn), bukan FK ke tabel ini.
 * PK = bigint; kolom `ulid` unik hanya pada entitas yang dipertukarkan lintas-service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institusi', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->nullable();
            $table->string('nama');
            $table->string('jenis')->default('prodi'); // fakultas/prodi
            $table->string('asosiasi_profesi')->nullable();
            $table->timestamps();
        });

        Schema::create('kurikulum', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique(); // kunci tukar lintas-service
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->string('kode')->nullable();
            $table->string('nama');
            $table->string('tahun');
            $table->string('status')->default('draft'); // draft/berlaku/arsip
            $table->date('tanggal_berlaku')->nullable();
            $table->date('tanggal_pensiun')->nullable();
            $table->foreignId('mengganti_id')->nullable()->constrained('kurikulum')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('dosen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->string('nidn');
            $table->string('nama');
            $table->timestamps();
            $table->unique(['institusi_id', 'nidn']);
        });

        Schema::create('mata_kuliah', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique(); // kunci tukar lintas-service
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->foreignId('kurikulum_id')->nullable()->constrained('kurikulum')->nullOnDelete();
            $table->string('kode_mk');
            $table->string('nama');
            $table->string('jenis_mk')->default('murni');   // murni(teori)/praktikum
            $table->string('sifat')->nullable();            // wajib/pilihan
            $table->string('rumpun')->nullable();
            $table->text('deskripsi_singkat')->nullable();
            $table->unsignedTinyInteger('sks_teori')->default(0);
            $table->unsignedTinyInteger('sks_praktik')->default(0);
            $table->unsignedTinyInteger('semester')->nullable();
            $table->string('prodi_kode')->nullable();
            $table->string('prasyarat_kode')->nullable();
            $table->timestamps();
            $table->unique(['kurikulum_id', 'kode_mk']);
            $table->index(['institusi_id', 'kode_mk']);
        });

        Schema::create('mk_pengampu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->string('kode_mk');
            $table->string('dosen_nidn');
            $table->string('peran')->default('anggota'); // koordinator/anggota
            $table->timestamps();
            $table->index(['institusi_id', 'kode_mk']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mk_pengampu');
        Schema::dropIfExists('mata_kuliah');
        Schema::dropIfExists('dosen');
        Schema::dropIfExists('kurikulum');
        Schema::dropIfExists('institusi');
    }
};
