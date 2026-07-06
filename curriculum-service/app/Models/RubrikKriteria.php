<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubrikKriteria extends Model
{
    protected $table = 'rubrik_kriteria';
    protected $guarded = [];

    protected $casts = [
        'bobot' => 'decimal:2',
        'deskriptor' => 'array',
        'urutan' => 'integer',
    ];

    public function rubrik(): BelongsTo
    {
        return $this->belongsTo(Rubrik::class, 'rubrik_id');
    }
}
