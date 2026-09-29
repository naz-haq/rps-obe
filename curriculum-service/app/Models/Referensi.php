<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referensi extends Model
{
    protected $table = 'referensi';
    protected $guarded = [];

    /** utama/pendukung + standar (guideline/farmakope) + jurnal (mutakhir). */
    public const TIPE = ['utama', 'pendukung', 'standar', 'jurnal'];

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }
}
