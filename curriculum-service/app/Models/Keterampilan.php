<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Keterampilan extends Model
{
    protected $table = 'keterampilan';
    protected $guarded = [];

    protected $casts = [
        'tingkat_kemampuan' => 'integer',
    ];

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }

    public function bahanKajian(): BelongsTo
    {
        return $this->belongsTo(BahanKajian::class, 'bahan_kajian_id');
    }

    public function taksonomi(): BelongsTo
    {
        return $this->belongsTo(Taksonomi::class, 'taksonomi_id');
    }
}
