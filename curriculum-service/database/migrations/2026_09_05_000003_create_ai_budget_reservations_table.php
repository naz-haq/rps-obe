<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_budget_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_kredensial_id')->constrained('ai_kredensial')->cascadeOnDelete();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->decimal('amount', 12, 6);
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['institusi_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_budget_reservations');
    }
};
