<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GenerateSession extends Model
{
    protected $table = 'generate_session';
    protected $guarded = [];

    protected $casts = [
        'draf' => 'array',
        'status_bagian' => 'array',
        'catatan_validasi' => 'array',
    ];

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class, 'mk_id');
    }

    public function rpsVersion(): BelongsTo
    {
        return $this->belongsTo(RpsVersion::class, 'rps_version_id');
    }
}
