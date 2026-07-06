<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lapisan AI: kredensial (BYOK per tenant/user, terenkripsi), log interaksi
 * (audit + biaya token), dan validasi anti-halusinasi (grounding + konteks pengganti).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_kredensial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable(); // null = kredensial institusi
            $table->string('provider'); // openai/anthropic/google/openrouter/..
            $table->text('api_key_encrypted');
            $table->string('model_default')->nullable();
            $table->string('mata_uang', 3)->default('USD');
            $table->decimal('anggaran', 12, 4)->nullable();       // batas biaya (kuota) per periode berjalan
            $table->decimal('saldo_provider', 12, 4)->nullable(); // cache saldo/kredit dari provider bila API mendukung
            $table->timestamp('saldo_diperbarui_at')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->index(['institusi_id', 'provider']);
        });

        Schema::create('ai_interaksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->nullable()->constrained('institusi')->cascadeOnDelete(); // null = panggilan sistem/non-tenant
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('entity_type')->nullable(); // MataKuliah/Cpl/RpsVersion/..
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('mode'); // generate/refine/validate_grounding/embedding/..
            $table->string('provider');
            $table->string('model');
            $table->longText('prompt')->nullable();
            $table->longText('response')->nullable();
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->decimal('biaya', 12, 6)->nullable();
            $table->string('status')->default('sukses'); // sukses/gagal/timeout
            $table->timestamps();
            $table->index(['institusi_id', 'entity_type', 'entity_id']);
        });

        Schema::create('ai_validasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_interaksi_id')->constrained('ai_interaksi')->cascadeOnDelete();
            $table->text('klaim'); // klaim/pernyataan yang divalidasi
            $table->string('status'); // grounded/tak_didukung/kontradiktif
            $table->json('bukti_chunk_ids')->nullable(); // referensi ke dokumen_chunk
            $table->decimal('skor_grounding', 5, 2)->nullable();
            $table->text('konteks_pengganti')->nullable(); // konteks benar pengganti hasil halu
            $table->string('tindakan')->nullable(); // terima/tolak/revisi_ulang
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_validasi');
        Schema::dropIfExists('ai_interaksi');
        Schema::dropIfExists('ai_kredensial');
    }
};
