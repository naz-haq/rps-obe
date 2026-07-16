<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah override model per-tugas (manual dari UI) ke AI_PENGATURAN.
 * Bentuk: {"generate":"gpt-5-4","validator":"gemini-flash-lite", ...}.
 * Menimpa pemetaan profil untuk task yang di-set; task lain ikut profil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_pengaturan', function (Blueprint $table) {
            $table->json('model_override')->nullable()->after('profil');
        });
    }

    public function down(): void
    {
        Schema::table('ai_pengaturan', function (Blueprint $table) {
            $table->dropColumn('model_override');
        });
    }
};
