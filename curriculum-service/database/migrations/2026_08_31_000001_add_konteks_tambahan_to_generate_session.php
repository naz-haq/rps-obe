<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rujukan tambahan dari dosen sebelum generate: kompetensi khusus MK,
 * Body of Knowledge, dan penekanan CPL khusus MK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generate_session', function (Blueprint $table) {
            $table->json('konteks_tambahan')->nullable()->after('catatan_validasi');
        });
    }

    public function down(): void
    {
        Schema::table('generate_session', function (Blueprint $table) {
            $table->dropColumn('konteks_tambahan');
        });
    }
};
