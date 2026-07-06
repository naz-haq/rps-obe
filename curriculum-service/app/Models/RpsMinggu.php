<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpsMinggu extends Model
{
    protected $table = 'rps_minggu';
    protected $guarded = [];

    protected $casts = [
        'minggu_ke' => 'integer',
        'estimasi_waktu' => 'array',
        'bobot_penilaian' => 'decimal:2',
    ];

    public function rpsVersion(): BelongsTo
    {
        return $this->belongsTo(RpsVersion::class, 'rps_version_id');
    }

    public function subCpmk(): BelongsTo
    {
        return $this->belongsTo(SubCpmk::class, 'sub_cpmk_id');
    }
}
