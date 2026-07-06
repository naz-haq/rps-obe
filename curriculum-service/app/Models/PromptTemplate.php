<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptTemplate extends Model
{
    protected $table = 'prompt_template';
    protected $guarded = [];

    protected $casts = [
        'skema_output' => 'array',
        'few_shot' => 'array',
        'versi' => 'integer',
        'aktif' => 'boolean',
    ];

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }
}
