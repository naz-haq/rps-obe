<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KerangkaAcuan extends Model
{
    protected $table = 'kerangka_acuan';
    protected $guarded = [];

    protected $casts = [
        'tanggal_berlaku' => 'date',
    ];

    public function badanRujukan(): BelongsTo
    {
        return $this->belongsTo(BadanRujukan::class, 'badan_rujukan_id');
    }

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(DokumenRujukan::class, 'dokumen_id');
    }

    public function butir(): HasMany
    {
        return $this->hasMany(ButirAcuan::class, 'kerangka_acuan_id');
    }
}
