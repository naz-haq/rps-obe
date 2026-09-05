<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akuntansi biaya & audit fallback (§4.3/§4.4):
 *  - requested_provider/model = model yang DIMINTA (sebelum fallback runtime),
 *    provider/model existing = yang BENAR-BENAR dijalankan (efektif).
 *  - fallback_reason = alasan berpindah ke mock saat provider nyata gagal.
 *  - billing_status = keandalan biaya: known/free/unknown/mock/cache.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_interaksi', function (Blueprint $table) {
            $table->string('requested_provider')->nullable()->after('model');
            $table->string('requested_model')->nullable()->after('requested_provider');
            $table->string('fallback_reason')->nullable()->after('requested_model');
            $table->string('billing_status')->nullable()->after('biaya'); // known/free/unknown/mock/cache
        });
    }

    public function down(): void
    {
        Schema::table('ai_interaksi', function (Blueprint $table) {
            $table->dropColumn(['requested_provider', 'requested_model', 'fallback_reason', 'billing_status']);
        });
    }
};
