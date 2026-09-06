<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generate_session', function (Blueprint $table) {
            // Hash draf saat commit terakhir; dipakai memutuskan apakah commit
            // berikutnya membuat versi RPS baru (draf berubah) atau menimpa versi
            // yang sama (tak ada perubahan).
            $table->string('committed_draf_hash', 64)->nullable()->after('rps_version_id');
        });
    }

    public function down(): void
    {
        Schema::table('generate_session', function (Blueprint $table) {
            $table->dropColumn('committed_draf_hash');
        });
    }
};
