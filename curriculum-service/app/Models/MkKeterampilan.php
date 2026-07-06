<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MkKeterampilan extends Model
{
    protected $table = 'mk_keterampilan';
    protected $guarded = [];

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }

    public function keterampilan(): BelongsTo
    {
        return $this->belongsTo(Keterampilan::class, 'keterampilan_id');
    }

    public function taksonomi(): BelongsTo
    {
        return $this->belongsTo(Taksonomi::class, 'taksonomi_id');
    }
}
