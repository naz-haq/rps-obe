<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kepatuhan & keterlacakan: pemenuhan butir acuan (polimorfik), sitasi sumber
 * (grounding AI/RAG), dan validasi overlap keterampilan antar-MK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemenuhan_acuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->foreignId('butir_acuan_id')->constrained('butir_acuan')->cascadeOnDelete();
            // POLIMORFIK: entity kurikulum yang memenuhi (cpl/mata_kuliah/profil_lulusan/dst)
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('status')->default('belum'); // terpenuhi/sebagian/belum/tidak_relevan
            $table->text('catatan')->nullable();
            $table->boolean('rekomendasi_ai')->default(false); // ditandai oleh AI
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamps();
            $table->index(['institusi_id', 'butir_acuan_id']);
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('source_citation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            // POLIMORFIK: entity OBE yang disitasi dari dokumen rujukan
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->foreignId('dokumen_id')->nullable()->constrained('dokumen_rujukan')->nullOnDelete();
            $table->unsignedInteger('halaman')->nullable();
            $table->text('cuplikan_teks')->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('validasi_overlap', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->foreignId('keterampilan_id')->constrained('keterampilan')->cascadeOnDelete();
            $table->json('mk_terlibat')->nullable();
            $table->string('status')->nullable(); // overlap/aman/perlu_review
            $table->text('analisis')->nullable();
            $table->text('rekomendasi')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validasi_overlap');
        Schema::dropIfExists('source_citation');
        Schema::dropIfExists('pemenuhan_acuan');
    }
};
