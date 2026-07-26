<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VMTS (Visi, Misi, Tujuan, Strategi) program studi — BERVERSI.
 * Satu prodi boleh punya beberapa versi VMTS (mis. antar periode/renstra).
 * Kurikulum memilih SATU versi (kurikulum.vmts_id) sehingga perubahan/penambahan
 * VMTS baru tidak mengubah dokumen kurikulum yang sudah dirumuskan.
 * misi/tujuan/strategi disimpan sebagai daftar bernomor (array item).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prodi_vmts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete(); // prodi
            $table->string('label');                 // mis. "VMTS 2024" / "Renstra 2024-2029"
            $table->text('visi')->nullable();
            $table->json('misi')->nullable();        // list<string> bernomor
            $table->json('tujuan')->nullable();      // list<string>
            $table->json('strategi')->nullable();    // list<string>
            $table->timestamps();
            $table->index('institusi_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prodi_vmts');
    }
};
