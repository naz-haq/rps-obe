<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matriks CPL x Bahan Kajian (Modul 1 — fondasi peta kurikulum).
 * Menandai bahan kajian mana yang menopang tiap CPL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpl_bahan_kajian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->foreignId('cpl_id')->constrained('cpl')->cascadeOnDelete();
            $table->foreignId('bahan_kajian_id')->constrained('bahan_kajian')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['cpl_id', 'bahan_kajian_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpl_bahan_kajian');
    }
};
