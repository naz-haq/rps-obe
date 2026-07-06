<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CpmkCpl extends Model
{
    protected $table = 'cpmk_cpl';
    protected $guarded = [];

    protected $casts = [
        'bobot' => 'decimal:2',
    ];

    public function cpmk(): BelongsTo
    {
        return $this->belongsTo(Cpmk::class, 'cpmk_id');
    }

    public function cpl(): BelongsTo
    {
        return $this->belongsTo(Cpl::class, 'cpl_id');
    }
}
