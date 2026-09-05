<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompt_template', function (Blueprint $table) {
            $table->boolean('use_default')->default(false);
        });

        // Stable global mutex even before any institution/global prompt exists.
        // Tenant writes use their existing institusi row instead.
        Schema::create('prompt_template_locks', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
        });
        DB::table('prompt_template_locks')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_template_locks');
        Schema::table('prompt_template', function (Blueprint $table) {
            $table->dropColumn('use_default');
        });
    }
};
