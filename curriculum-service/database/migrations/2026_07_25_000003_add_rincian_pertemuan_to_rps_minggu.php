<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rincian per-pertemuan untuk tiap pekan RPS (MK blok/profesi yang punya
 * >1 pertemuan/pekan). Diisi oleh generate lanjutan berbasis rencana mingguan.
 * Bentuk: [{pertemuan_ke, topik, aktivitas, metode, durasi_menit}, ...]
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rps_minggu', function (Blueprint $table) {
            $table->json('rincian_pertemuan')->nullable()->after('estimasi_waktu');
        });
    }

    public function down(): void
    {
        Schema::table('rps_minggu', function (Blueprint $table) {
            $table->dropColumn('rincian_pertemuan');
        });
    }
};
