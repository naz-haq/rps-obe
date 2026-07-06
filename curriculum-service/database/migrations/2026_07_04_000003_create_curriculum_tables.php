<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inti kurikulum OBE: Profil Lulusan, CPL (+aspek SN-Dikti), Bahan Kajian,
 * Keterampilan, dan matriks (PL×CPL, MK×CPL, MK×Keterampilan).
 * OBE merujuk MK lewat kode_mk (kunci natural), bukan FK ke mata_kuliah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taksonomi', function (Blueprint $table) {
            $table->id();
            // null = seed global (bawaan); tenant boleh menambah sendiri
            $table->foreignId('institusi_id')->nullable()->constrained('institusi')->cascadeOnDelete();
            $table->string('domain');   // kognitif/afektif/psikomotorik
            $table->string('kerangka'); // bloom_anderson/krathwohl/dave/simpson
            $table->string('kode');     // C1..C6 / A1..A5 / P1..P7
            $table->string('nama');     // Mengingat/Menerima/Imitasi/..
            $table->unsignedTinyInteger('level');
            $table->text('deskripsi')->nullable();
            $table->json('kata_kerja')->nullable(); // kata kerja operasional terukur
            $table->timestamps();
            $table->index(['domain', 'kerangka']);
        });

        Schema::create('profil_lulusan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->foreignId('kurikulum_id')->nullable()->constrained('kurikulum')->cascadeOnDelete();
            $table->string('kode');
            $table->text('deskripsi');
            $table->timestamps();
            $table->unique(['kurikulum_id', 'kode']);
        });

        Schema::create('cpl', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->foreignId('kurikulum_id')->nullable()->constrained('kurikulum')->cascadeOnDelete();
            $table->string('kode');
            $table->text('deskripsi');
            // sikap/pengetahuan/keterampilan_umum/keterampilan_khusus (SN-Dikti)
            $table->string('aspek')->nullable();
            $table->string('level_kkni')->nullable();
            $table->string('sumber')->nullable(); // SN-Dikti/asosiasi/prodi
            $table->timestamps();
            $table->unique(['kurikulum_id', 'kode']);
        });

        Schema::create('target_cpl', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->foreignId('cpl_id')->constrained('cpl')->cascadeOnDelete();
            $table->string('angkatan')->nullable(); // atau periode
            $table->decimal('ambang_nilai', 5, 2)->nullable();      // nilai minimal lulus CPL
            $table->decimal('persentase_target', 5, 2)->nullable(); // % mhs yang harus mencapai ambang
            $table->timestamps();
        });

        Schema::create('pl_cpl', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->foreignId('profil_lulusan_id')->constrained('profil_lulusan')->cascadeOnDelete();
            $table->foreignId('cpl_id')->constrained('cpl')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['profil_lulusan_id', 'cpl_id']);
        });

        Schema::create('bahan_kajian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->foreignId('kurikulum_id')->nullable()->constrained('kurikulum')->cascadeOnDelete();
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        Schema::create('keterampilan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->foreignId('bahan_kajian_id')->constrained('bahan_kajian')->cascadeOnDelete();
            $table->text('deskripsi');
            $table->string('domain')->nullable(); // kognitif/psikomotorik/afektif
            $table->foreignId('taksonomi_id')->nullable()->constrained('taksonomi')->nullOnDelete();
            $table->unsignedTinyInteger('tingkat_kemampuan')->nullable();
            $table->string('sumber')->nullable();
            $table->timestamps();
        });

        Schema::create('mk_cpl', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->string('kode_mk');
            $table->foreignId('cpl_id')->constrained('cpl')->cascadeOnDelete();
            $table->decimal('bobot', 6, 2)->nullable();
            $table->timestamps();
            $table->index(['institusi_id', 'kode_mk']);
        });

        Schema::create('mk_keterampilan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->string('kode_mk');
            $table->foreignId('keterampilan_id')->constrained('keterampilan')->cascadeOnDelete();
            $table->text('fokus_spesifik')->nullable();
            $table->foreignId('taksonomi_id')->nullable()->constrained('taksonomi')->nullOnDelete();
            $table->timestamps();
            $table->index(['institusi_id', 'kode_mk']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mk_keterampilan');
        Schema::dropIfExists('mk_cpl');
        Schema::dropIfExists('keterampilan');
        Schema::dropIfExists('bahan_kajian');
        Schema::dropIfExists('pl_cpl');
        Schema::dropIfExists('target_cpl');
        Schema::dropIfExists('cpl');
        Schema::dropIfExists('profil_lulusan');
        Schema::dropIfExists('taksonomi');
    }
};
