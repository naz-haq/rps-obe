<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Logo institusi (kop dokumen) dan spesimen tanda tangan elektronik pengguna. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institusi', function (Blueprint $table) {
            $table->string('logo_path')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('ttd_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ttd_path');
        });

        Schema::table('institusi', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
