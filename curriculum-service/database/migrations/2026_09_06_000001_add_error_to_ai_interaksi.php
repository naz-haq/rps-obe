<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnosis kegagalan panggilan AI (Error B): simpan pesan error asli provider
 * (mis. "HTTP 429: rate limit", timeout) pada baris status 'gagal' agar akar
 * penyebab bisa dianalisis dari DB produksi — log file tidak tersedia di kontainer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_interaksi', function (Blueprint $table) {
            $table->string('error', 500)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('ai_interaksi', function (Blueprint $table) {
            $table->dropColumn('error');
        });
    }
};
