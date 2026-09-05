<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_pengaturan', function (Blueprint $table) {
            $table->string('embedding_provider')->nullable()->after('model_override');
            $table->string('embedding_model')->nullable()->after('embedding_provider');
            $table->unsignedInteger('embedding_dimensions')->nullable()->after('embedding_model');
        });
    }

    public function down(): void
    {
        Schema::table('ai_pengaturan', function (Blueprint $table) {
            $table->dropColumn(['embedding_provider', 'embedding_model', 'embedding_dimensions']);
        });
    }
};
