<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ButirAcuan extends Model
{
    protected $table = 'butir_acuan';
    protected $guarded = [];

    protected $casts = [
        'wajib' => 'boolean',
        'urutan' => 'integer',
    ];

    public function kerangka(): BelongsTo
    {
        return $this->belongsTo(KerangkaAcuan::class, 'kerangka_acuan_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ButirAcuan::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ButirAcuan::class, 'parent_id');
    }

    public function pemenuhan(): HasMany
    {
        return $this->hasMany(PemenuhanAcuan::class, 'butir_acuan_id');
    }
}
