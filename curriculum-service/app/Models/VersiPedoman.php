<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersiPedoman extends Model
{
    protected $table = 'versi_pedoman';
    protected $guarded = [];

    protected $casts = [
        'tanggal_berlaku' => 'date',
        'tanggal_nonaktif' => 'date',
        'mk_terdampak' => 'array',
    ];

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(DokumenRujukan::class, 'dokumen_id');
    }
}
