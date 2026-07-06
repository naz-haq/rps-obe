<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NIDN jadi identitas login utama (unik); email opsional.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Kolom nidn bisa jadi belum dibuat (urutan migrasi profil ada di tanggal
        // lebih baru). Tambahkan lebih dulu jika belum ada agar unique bisa dipasang.
        if (! Schema::hasColumn('users', 'nidn')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('nidn')->nullable()->after('email');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->unique('nidn');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nidn']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
