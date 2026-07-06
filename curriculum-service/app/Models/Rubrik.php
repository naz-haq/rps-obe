<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rubrik extends Model
{
    protected $table = 'rubrik';
    protected $guarded = [];

    protected $casts = [
        'jumlah_level_skala' => 'integer',
        'label_skala' => 'array',
    ];

    public function komponenPenilaian(): BelongsTo
    {
        return $this->belongsTo(KomponenPenilaian::class, 'komponen_penilaian_id');
    }

    public function kriteria(): HasMany
    {
        return $this->hasMany(RubrikKriteria::class, 'rubrik_id');
    }
}
