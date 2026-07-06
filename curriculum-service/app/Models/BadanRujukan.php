<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BadanRujukan extends Model
{
    protected $table = 'badan_rujukan';
    protected $guarded = [];

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }

    public function kerangkaAcuan(): HasMany
    {
        return $this->hasMany(KerangkaAcuan::class, 'badan_rujukan_id');
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(DokumenRujukan::class, 'badan_rujukan_id');
    }
}
