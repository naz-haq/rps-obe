<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateRps extends Model
{
    protected $table = 'template_rps';
    protected $guarded = [];

    protected $casts = [
        'struktur_kolom' => 'array',
    ];

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }

    public function dokumenAsal(): BelongsTo
    {
        return $this->belongsTo(DokumenRujukan::class, 'dokumen_asal_id');
    }
}
