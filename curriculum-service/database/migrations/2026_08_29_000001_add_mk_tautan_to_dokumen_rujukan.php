<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautan dokumen rujukan ke mata kuliah (satu dokumen boleh dipakai banyak MK)
 * + sidik jari berkas (sha256) untuk deteksi duplikat sebelum menyimpan ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dokumen_rujukan', function (Blueprint $table) {
            $table->string('file_hash', 64)->nullable()->index()->after('file_path');
        });

        Schema::create('mk_dokumen_rujukan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->string('kode_mk'); // kunci natural, konsisten dgn mk_bahan_kajian/referensi
            $table->foreignId('dokumen_rujukan_id')->constrained('dokumen_rujukan')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['institusi_id', 'kode_mk', 'dokumen_rujukan_id'], 'mk_dokumen_rujukan_unik');
            $table->index(['institusi_id', 'kode_mk']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mk_dokumen_rujukan');
        Schema::table('dokumen_rujukan', function (Blueprint $table) {
            $table->dropColumn('file_hash');
        });
    }
};
