<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DokumenRujukan extends Model
{
    protected $table = 'dokumen_rujukan';
    protected $guarded = [];

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }

    public function badanRujukan(): BelongsTo
    {
        return $this->belongsTo(BadanRujukan::class, 'badan_rujukan_id');
    }

    public function versiPedoman(): HasMany
    {
        return $this->hasMany(VersiPedoman::class, 'dokumen_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DokumenChunk::class, 'dokumen_id');
    }
}
