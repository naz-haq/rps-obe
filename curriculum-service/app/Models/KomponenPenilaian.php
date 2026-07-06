<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class KomponenPenilaian extends Model
{
    protected $table = 'komponen_penilaian';
    protected $guarded = [];

    protected $casts = [
        'bobot_persen' => 'decimal:2',
        'minggu_ke' => 'integer',
    ];

    public function rpsVersion(): BelongsTo
    {
        return $this->belongsTo(RpsVersion::class, 'rps_version_id');
    }

    public function subCpmk(): BelongsTo
    {
        return $this->belongsTo(SubCpmk::class, 'sub_cpmk_id');
    }

    public function rubrik(): HasOne
    {
        return $this->hasOne(Rubrik::class, 'komponen_penilaian_id');
    }
}
