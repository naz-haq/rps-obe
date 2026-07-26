<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atribut identitas untuk Buku/Dokumen Kurikulum KPT 2024 Bab I & IV.
 * Field prodi (jenjang/gelar/akreditasi) menempel pada baris institusi jenis
 * 'prodi'; nilai_institusi (University Value) pada baris jenis 'universitas'.
 * VMTS (visi/misi/tujuan/strategi) TIDAK di sini — dipisah ke tabel berversi
 * prodi_vmts agar bisa dipilih per kurikulum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institusi', function (Blueprint $table) {
            $table->string('jenjang')->nullable()->after('asosiasi_profesi');       // Sarjana (S1), Profesi, dst.
            $table->string('gelar')->nullable()->after('jenjang');                   // Gelar lulusan, mis. S.Farm.
            $table->string('akreditasi')->nullable()->after('gelar');                // Peringkat + SK/tahun
            $table->text('nilai_institusi')->nullable()->after('akreditasi');        // University Value (untuk universitas)
        });
    }

    public function down(): void
    {
        Schema::table('institusi', function (Blueprint $table) {
            $table->dropColumn(['jenjang', 'gelar', 'akreditasi', 'nilai_institusi']);
        });
    }
};
