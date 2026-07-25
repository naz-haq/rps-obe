<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Override per-MK jumlah pertemuan per pekan.
 *  - null  → ikut aturan (durasi_sesi untuk kelas; hari_per_minggu untuk profesi/PKPA).
 *  - >0    → menimpa aturan (mis. blok padat 6 pertemuan/pekan, PKPA 6 hari/pekan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mata_kuliah', function (Blueprint $table) {
            $table->unsignedSmallInteger('jumlah_pertemuan')->nullable()->after('jumlah_minggu');
        });
    }

    public function down(): void
    {
        Schema::table('mata_kuliah', function (Blueprint $table) {
            $table->dropColumn('jumlah_pertemuan');
        });
    }
};
