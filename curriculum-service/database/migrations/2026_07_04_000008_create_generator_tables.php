<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mesin generator: sesi penyusunan bertahap (staging draf per bagian) +
 * pustaka prompt template versioned per jenis output & jenis MK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generate_session', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->foreignId('mk_id')->nullable()->constrained('mata_kuliah')->nullOnDelete();
            $table->foreignId('rps_version_id')->nullable()->constrained('rps_version')->nullOnDelete();
            $table->string('sumber')->default('baru'); // baru/impor_rps_lama/copy_tahun_lalu
            $table->string('tahap')->nullable(); // bagian pipeline yang sedang dikerjakan
            $table->json('draf')->nullable(); // staging hasil generate belum di-commit
            $table->json('status_bagian')->nullable(); // {cpmk:done, mingguan:draft, ...}
            $table->json('catatan_validasi')->nullable(); // ringkasan grounding per tahap {cpmk:{...}}
            $table->string('status')->default('berjalan'); // berjalan/selesai/batal
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->index(['institusi_id', 'mk_id']);
        });

        Schema::create('prompt_template', function (Blueprint $table) {
            $table->id();
            // null = template bawaan global; tenant boleh override
            $table->foreignId('institusi_id')->nullable()->constrained('institusi')->cascadeOnDelete();
            $table->string('jenis_output'); // cpmk/sub_cpmk/mingguan/rubrik/deskripsi_mk/..
            $table->string('jenis_mk')->nullable(); // murni/praktikum/null=semua
            $table->text('sistem_prompt');
            $table->json('skema_output')->nullable(); // JSON schema structured output
            $table->json('few_shot')->nullable();
            $table->unsignedInteger('versi')->default(1);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->index(['jenis_output', 'jenis_mk']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_template');
        Schema::dropIfExists('generate_session');
    }
};
