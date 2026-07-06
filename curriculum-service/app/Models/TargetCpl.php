<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TargetCpl extends Model
{
    protected $table = 'target_cpl';
    protected $guarded = [];

    protected $casts = [
        'ambang_nilai' => 'decimal:2',
        'persentase_target' => 'decimal:2',
    ];

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }

    public function cpl(): BelongsTo
    {
        return $this->belongsTo(Cpl::class, 'cpl_id');
    }
}
