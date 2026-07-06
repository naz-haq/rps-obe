<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hierarki institusi: Prodi terikat pada Fakultas (berjenjang).
 * parent_id null = unit puncak (fakultas); prodi menunjuk fakultas induknya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institusi', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('jenis')
                ->constrained('institusi')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('institusi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
