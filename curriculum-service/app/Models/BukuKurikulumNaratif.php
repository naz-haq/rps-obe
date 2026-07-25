<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BukuKurikulumNaratif extends Model
{
    protected $table = 'buku_kurikulum_naratif';
    protected $guarded = [];

    protected $casts = [
        'naratif'         => 'array',
        'digenerate_pada' => 'datetime',
    ];

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class, 'kurikulum_id');
    }
}
