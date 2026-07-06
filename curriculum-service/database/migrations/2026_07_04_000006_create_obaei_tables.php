<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OBAEI (Outcome-Based Accreditation/Education Improvement): capaian mahasiswa
 * agregat, evaluasi CPL, dan tindak lanjut (closing the loop).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capaian_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->string('kode_mk');
            $table->foreignId('sub_cpmk_id')->nullable()->constrained('sub_cpmk')->nullOnDelete();
            $table->foreignId('cpmk_id')->nullable()->constrained('cpmk')->nullOnDelete();
            $table->string('angkatan')->nullable();
            $table->unsignedInteger('jumlah_mahasiswa')->nullable();
            $table->decimal('nilai_rata_rata', 5, 2)->nullable();
            $table->decimal('persentase_capaian_minimal', 5, 2)->nullable();
            $table->timestamps();
            $table->index(['institusi_id', 'kode_mk']);
        });

        Schema::create('evaluasi_cpl', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->foreignId('cpl_id')->constrained('cpl')->cascadeOnDelete();
            $table->string('periode')->nullable();
            $table->text('ringkasan_naratif')->nullable();
            $table->string('status')->default('draft'); // draft/final
            $table->unsignedBigInteger('dibuat_oleh')->nullable();
            $table->unsignedBigInteger('difinalisasi_oleh')->nullable();
            $table->timestamps();
        });

        Schema::create('tindak_lanjut', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->foreignId('evaluasi_cpl_id')->constrained('evaluasi_cpl')->cascadeOnDelete();
            $table->foreignId('sub_cpmk_id')->nullable()->constrained('sub_cpmk')->nullOnDelete();
            $table->text('catatan');
            $table->string('prioritas')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tindak_lanjut');
        Schema::dropIfExists('evaluasi_cpl');
        Schema::dropIfExists('capaian_mahasiswa');
    }
};
