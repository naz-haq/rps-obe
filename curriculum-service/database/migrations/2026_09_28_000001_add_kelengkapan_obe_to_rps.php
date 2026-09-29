<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelengkapan dokumen RPS format OBE (formulir mutu): identitas dokumen,
 * otorisasi, media/LMS/proporsi, peran CPL, lampiran; serta kolom pekanan
 * Media/Sumber dan Bukti/Produk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rps_version', function (Blueprint $table) {
            $table->string('tahun_akademik', 20)->nullable()->after('tanggal_penyusunan');
            $table->date('berlaku_mulai')->nullable()->after('tahun_akademik');
            $table->json('kelengkapan')->nullable()->after('berlaku_mulai');
        });

        Schema::table('rps_minggu', function (Blueprint $table) {
            $table->text('media_sumber')->nullable()->after('pengalaman_belajar');
            $table->text('bukti_produk')->nullable()->after('media_sumber');
        });
    }

    public function down(): void
    {
        Schema::table('rps_minggu', function (Blueprint $table) {
            $table->dropColumn(['media_sumber', 'bukti_produk']);
        });

        Schema::table('rps_version', function (Blueprint $table) {
            $table->dropColumn(['tahun_akademik', 'berlaku_mulai', 'kelengkapan']);
        });
    }
};
