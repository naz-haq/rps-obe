<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KonfigurasiAturan extends Model
{
    protected $table = 'konfigurasi_aturan';
    protected $guarded = [];

    protected $casts = [
        'nilai' => 'array',
    ];

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }

    public function badanRujukan(): BelongsTo
    {
        return $this->belongsTo(BadanRujukan::class, 'badan_rujukan_id');
    }

    public function referensiDokumen(): BelongsTo
    {
        return $this->belongsTo(DokumenRujukan::class, 'referensi_dokumen_id');
    }
}
