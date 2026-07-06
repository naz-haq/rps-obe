<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CplBahanKajian extends Model
{
    protected $table = 'cpl_bahan_kajian';
    protected $guarded = [];

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }

    public function cpl(): BelongsTo
    {
        return $this->belongsTo(Cpl::class, 'cpl_id');
    }

    public function bahanKajian(): BelongsTo
    {
        return $this->belongsTo(BahanKajian::class, 'bahan_kajian_id');
    }
}
