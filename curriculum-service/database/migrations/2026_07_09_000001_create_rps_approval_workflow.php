<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul 4 — Workflow Approval.
 * Menambah kolom jejak persetujuan pada rps_version + tabel riwayat transisi
 * status (audit trail: siapa, kapan, dari→ke, catatan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rps_version', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('koordinator_mk');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('catatan_review')->nullable()->after('approved_at');
        });

        Schema::create('rps_approval_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->nullable()->constrained('institusi')->nullOnDelete();
            $table->foreignId('rps_version_id')->constrained('rps_version')->cascadeOnDelete();
            $table->string('aksi'); // ajukan/setujui/revisi/tarik
            $table->string('dari_status')->nullable();
            $table->string('ke_status');
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_nama')->nullable();
            $table->timestamps();
            $table->index('rps_version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rps_approval_log');
        Schema::table('rps_version', function (Blueprint $table) {
            $table->dropColumn(['submitted_at', 'approved_at', 'catatan_review']);
        });
    }
};
