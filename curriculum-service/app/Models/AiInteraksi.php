<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiInteraksi extends Model
{
    protected $table = 'ai_interaksi';
    protected $guarded = [];

    protected $casts = [
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
        'biaya' => 'decimal:6',
    ];

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }

    public function validasi(): HasMany
    {
        return $this->hasMany(AiValidasi::class, 'ai_interaksi_id');
    }
}
