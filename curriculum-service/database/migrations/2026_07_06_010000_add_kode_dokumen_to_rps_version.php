<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rps_version', function (Blueprint $table) {
            $table->string('kode_dokumen')->nullable()->after('bahasa');
        });
    }

    public function down(): void
    {
        Schema::table('rps_version', function (Blueprint $table) {
            $table->dropColumn('kode_dokumen');
        });
    }
};
