<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dukung BANYAK level taksonomi per CPMK & Sub-CPMK (mis. C4 + A3 + P2 untuk MK
 * praktikum yang menggabung ranah kognitif, afektif, psikomotor).
 * `taksonomi_id` tetap dipertahankan sebagai taksonomi PRIMER (kode pertama)
 * untuk relasi/join yang sudah ada; daftar lengkap disimpan sebagai JSON kode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cpmk', function (Blueprint $table) {
            $table->json('taksonomi_kode')->nullable()->after('taksonomi_id');
        });
        Schema::table('sub_cpmk', function (Blueprint $table) {
            $table->json('taksonomi_kode')->nullable()->after('taksonomi_id');
        });
    }

    public function down(): void
    {
        Schema::table('cpmk', function (Blueprint $table) {
            $table->dropColumn('taksonomi_kode');
        });
        Schema::table('sub_cpmk', function (Blueprint $table) {
            $table->dropColumn('taksonomi_kode');
        });
    }
};
