<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Narasi Buku Kurikulum yang tersimpan (hasil generate AI yang sudah ditinjau).
 * Dipisah dari data deterministik: preview & unduh memakai narasi tersimpan ini
 * sehingga dokumen yang diunduh persis seperti yang ditinjau pengguna.
 * Bentuk naratif: {pengantar, profil_lulusan, cpl, mata_kuliah} (string per bagian).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buku_kurikulum_naratif', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kurikulum_id')->unique()->constrained('kurikulum')->cascadeOnDelete();
            $table->json('naratif')->nullable();
            $table->timestamp('digenerate_pada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buku_kurikulum_naratif');
    }
};
