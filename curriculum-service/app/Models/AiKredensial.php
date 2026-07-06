<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiKredensial extends Model
{
    protected $table = 'ai_kredensial';
    protected $guarded = [];

    protected $hidden = ['api_key_encrypted'];

    protected $casts = [
        'aktif' => 'boolean',
        'api_key_encrypted' => 'encrypted',
        'anggaran' => 'decimal:4',
        'saldo_provider' => 'decimal:4',
        'saldo_diperbarui_at' => 'datetime',
    ];

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }
}
