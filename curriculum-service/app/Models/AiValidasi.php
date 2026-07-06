<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiValidasi extends Model
{
    protected $table = 'ai_validasi';
    protected $guarded = [];

    protected $casts = [
        'bukti_chunk_ids' => 'array',
        'skor_grounding' => 'decimal:2',
    ];

    public function interaksi(): BelongsTo
    {
        return $this->belongsTo(AiInteraksi::class, 'ai_interaksi_id');
    }
}
