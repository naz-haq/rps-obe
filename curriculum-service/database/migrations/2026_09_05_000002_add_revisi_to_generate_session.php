<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 candidate-patch: penghitung revisi draf untuk optimistic locking.
 * Setiap penerapan usulan per-item menaikkan revisi; apply memakai base_revisi
 * → bila berbeda, ditolak 409 (konflik) agar tak menimpa perubahan terbaru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generate_session', function (Blueprint $table) {
            $table->unsignedInteger('revisi')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('generate_session', function (Blueprint $table) {
            $table->dropColumn('revisi');
        });
    }
};
