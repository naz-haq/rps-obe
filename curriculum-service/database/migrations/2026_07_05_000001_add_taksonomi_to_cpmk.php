<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fitur E: level taksonomi (Bloom) pada CPMK.
 * Diperlukan untuk validasi keselarasan taksonomi CPMK vs Sub-CPMK
 * (level kognitif Sub-CPMK tidak boleh di bawah target CPMK induk).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cpmk', function (Blueprint $table) {
            $table->foreignId('taksonomi_id')->nullable()->after('deskripsi')
                ->constrained('taksonomi')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cpmk', function (Blueprint $table) {
            $table->dropConstrainedForeignId('taksonomi_id');
        });
    }
};
