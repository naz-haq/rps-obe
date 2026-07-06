<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubCpmk extends Model
{
    protected $table = 'sub_cpmk';
    protected $guarded = [];

    protected $casts = [
        'minggu_mulai' => 'integer',
        'minggu_selesai' => 'integer',
        'bobot_persen' => 'decimal:2',
        'taksonomi_kode' => 'array',
    ];

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }

    public function cpmk(): BelongsTo
    {
        return $this->belongsTo(Cpmk::class, 'cpmk_id');
    }

    public function taksonomi(): BelongsTo
    {
        return $this->belongsTo(Taksonomi::class, 'taksonomi_id');
    }

    public function indikator(): HasMany
    {
        return $this->hasMany(Indikator::class, 'sub_cpmk_id');
    }
}
