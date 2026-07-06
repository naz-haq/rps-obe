<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matriks Bahan Kajian x Mata Kuliah (KPT — pembentukan mata kuliah dari bahan
 * kajian). Menandai bahan kajian mana yang dibungkus tiap mata kuliah, sebagai
 * acuan peninjauan kembali struktur kurikulum (traceability BK -> MK).
 * Mata kuliah dirujuk via kode_mk (konsisten dengan mk_cpl).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mk_bahan_kajian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->string('kode_mk');
            $table->foreignId('bahan_kajian_id')->constrained('bahan_kajian')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['kode_mk', 'bahan_kajian_id']);
            $table->index(['institusi_id', 'kode_mk']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mk_bahan_kajian');
    }
};
