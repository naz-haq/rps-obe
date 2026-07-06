<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profil user untuk Auth & RBAC (Tahap 1 standalone, Sanctum + spatie/permission).
 * institusi_id = tautan ke prodi/fakultas (institusi.jenis). Belum penyekatan penuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('institusi_id')->nullable()->after('id')
                ->constrained('institusi')->nullOnDelete();
            $table->string('nidn')->nullable()->after('email');
            $table->string('jabatan')->nullable()->after('nidn');
            $table->boolean('is_active')->default(true)->after('jabatan');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institusi_id');
            $table->dropColumn(['nidn', 'jabatan', 'is_active']);
        });
    }
};
