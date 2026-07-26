<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kurikulum memilih SATU versi VMTS prodi (Bab IV Dokumen Kurikulum KPT 2024).
 * Nullable: bila belum dipilih, bagian VMTS tampil sebagai placeholder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kurikulum', function (Blueprint $table) {
            $table->foreignId('vmts_id')->nullable()->after('mengganti_id')
                ->constrained('prodi_vmts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kurikulum', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vmts_id');
        });
    }
};
