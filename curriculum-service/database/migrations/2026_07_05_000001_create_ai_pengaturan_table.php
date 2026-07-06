<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI_PENGATURAN — override profil AI aktif tanpa mengubah kode.
 * Satu baris global (institusi_id NULL) sebagai default seluruh aplikasi,
 * plus baris per-tenant opsional untuk menimpa profil khusus tenant.
 * Diisi/diubah lewat UI (endpoint /api/v1/ai/pengaturan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_pengaturan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->nullable()->constrained('institusi')->cascadeOnDelete();
            $table->string('profil')->default('simulasi'); // produksi/simulasi
            $table->unsignedBigInteger('diubah_oleh')->nullable();
            $table->timestamps();
            $table->unique('institusi_id'); // 1 baris per tenant; NULL = global default
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_pengaturan');
    }
};
