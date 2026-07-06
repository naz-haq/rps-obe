<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perluas template_rps untuk menyimpan BERKAS template/format cetak RPS
 * (docx/xlsx/html/pdf) agar ekstraksi cetak seragam. struktur_kolom dibuat
 * nullable karena template berbasis berkas tak selalu butuh definisi kolom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_rps', function (Blueprint $table) {
            $table->string('berkas_path')->nullable()->after('nama');
            $table->string('berkas_nama_asli')->nullable()->after('berkas_path');
            $table->string('format', 20)->nullable()->after('berkas_nama_asli');
            $table->text('keterangan')->nullable()->after('format');
            $table->boolean('is_active')->default(false)->after('keterangan');
        });

        // struktur_kolom: longgarkan agar template berkas boleh kosong.
        Schema::table('template_rps', function (Blueprint $table) {
            $table->json('struktur_kolom')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('template_rps', function (Blueprint $table) {
            $table->dropColumn(['berkas_path', 'berkas_nama_asli', 'format', 'keterangan', 'is_active']);
        });
    }
};
