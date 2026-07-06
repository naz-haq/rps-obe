<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tata kelola: notifikasi & audit log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifikasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('jenis');
            $table->text('konten');
            $table->string('status')->default('unread'); // unread/read
            $table->timestamps();
            $table->index(['institusi_id', 'user_id', 'status']);
        });

        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->nullable()->constrained('institusi')->nullOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('entity')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['entity', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('notifikasi');
    }
};
