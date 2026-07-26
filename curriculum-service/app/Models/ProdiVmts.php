<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdiVmts extends Model
{
    protected $table = 'prodi_vmts';
    protected $guarded = [];

    protected $casts = [
        'misi'     => 'array',
        'tujuan'   => 'array',
        'strategi' => 'array',
    ];

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }
}
