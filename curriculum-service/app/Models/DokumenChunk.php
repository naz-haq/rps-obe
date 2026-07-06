<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DokumenChunk extends Model
{
    protected $table = 'dokumen_chunk';
    protected $guarded = [];

    protected $casts = [
        'urutan' => 'integer',
        'halaman' => 'integer',
        'embedding' => 'array',
        'token_count' => 'integer',
    ];

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(DokumenRujukan::class, 'dokumen_id');
    }
}
