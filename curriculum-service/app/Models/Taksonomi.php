<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Taksonomi extends Model
{
    protected $table = 'taksonomi';
    protected $guarded = [];

    protected $casts = [
        'level' => 'integer',
        'kata_kerja' => 'array',
    ];

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }
}
