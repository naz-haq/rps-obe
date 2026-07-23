<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dukungan durasi variabel per Mata Kuliah:
 *  - `pola`         = format pelaksanaan: reguler | blok | profesi.
 *  - `jumlah_minggu`= jumlah pekan efektif MK ini (nullable → pakai default global
 *                     `konfigurasi_aturan.jumlah_minggu.minggu_efektif`, mis. 16).
 *
 * Diperlukan karena satu kurikulum dapat memuat MK reguler (16 pekan), MK BLOK
 * (durasi bervariasi), dan MK PRAKTEK PROFESI/klinik (durasi dari SKS) sekaligus.
 * Jumlah pekan = fakta kaku → diisi/dikonfirmasi manusia (Kaprodi), bukan AI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mata_kuliah', function (Blueprint $table) {
            $table->string('pola')->default('reguler')->after('jenis_mk'); // reguler/blok/profesi
            $table->unsignedSmallInteger('jumlah_minggu')->nullable()->after('pola');
        });
    }

    public function down(): void
    {
        Schema::table('mata_kuliah', function (Blueprint $table) {
            $table->dropColumn(['pola', 'jumlah_minggu']);
        });
    }
};
