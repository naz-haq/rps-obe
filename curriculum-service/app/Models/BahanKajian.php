<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BahanKajian extends Model
{
    protected $table = 'bahan_kajian';
    protected $guarded = [];

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class, 'kurikulum_id');
    }

    public function keterampilan(): HasMany
    {
        return $this->hasMany(Keterampilan::class, 'bahan_kajian_id');
    }
}
