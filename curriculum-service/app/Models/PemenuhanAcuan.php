<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PemenuhanAcuan extends Model
{
    protected $table = 'pemenuhan_acuan';
    protected $guarded = [];

    protected $casts = [
        'rekomendasi_ai' => 'boolean',
    ];

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }

    public function butir(): BelongsTo
    {
        return $this->belongsTo(ButirAcuan::class, 'butir_acuan_id');
    }

    public function entity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id');
    }
}
