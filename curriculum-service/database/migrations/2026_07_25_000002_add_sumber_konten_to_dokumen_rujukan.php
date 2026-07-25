<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda peran AI per dokumen rujukan: sumber_konten = dokumen bicara
 * KEILMUAN tenant (boleh membentuk hasil generate & jadi bukti grounding).
 * false = rujukan format/template saja (tidak dipakai sebagai sumber substansi).
 * Default false → tenant menandai sendiri dokumen mana yang keilmuan (opt-in).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dokumen_rujukan', function (Blueprint $table) {
            $table->boolean('sumber_konten')->default(false)->after('jenis');
        });
    }

    public function down(): void
    {
        Schema::table('dokumen_rujukan', function (Blueprint $table) {
            $table->dropColumn('sumber_konten');
        });
    }
};
