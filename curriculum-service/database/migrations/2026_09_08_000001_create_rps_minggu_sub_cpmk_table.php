<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu pekan dapat menyasar beberapa Sub-CPMK (mis. UTS/UAS terintegrasi).
 * Kolom rps_minggu.sub_cpmk_id tetap dipakai sebagai Sub-CPMK UTAMA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rps_minggu_sub_cpmk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rps_minggu_id')->constrained('rps_minggu')->cascadeOnDelete();
            $table->foreignId('sub_cpmk_id')->constrained('sub_cpmk')->cascadeOnDelete();
            $table->unsignedTinyInteger('urutan')->default(0);
            $table->timestamps();
            $table->unique(['rps_minggu_id', 'sub_cpmk_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rps_minggu_sub_cpmk');
    }
};
