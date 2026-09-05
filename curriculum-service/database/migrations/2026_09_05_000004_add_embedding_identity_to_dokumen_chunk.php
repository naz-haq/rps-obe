<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dokumen_chunk', function (Blueprint $table) {
            // Deliberately NO backfill: legacy vectors have unknown model/text
            // provenance. Reindex existing documents before they can be searched.
            // Metadata: provider/model/dimensions/mock/endpoint_hash/input_type,
            // exact text_hash and versioned signature; never API credentials.
            $table->json('embedding_identity')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dokumen_chunk', function (Blueprint $table) {
            $table->dropColumn('embedding_identity');
        });
    }
};
