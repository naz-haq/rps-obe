<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RpsMinggu extends Model
{
    protected $table = 'rps_minggu';
    protected $guarded = [];

    protected $casts = [
        'minggu_ke' => 'integer',
        'estimasi_waktu' => 'array',
        'rincian_pertemuan' => 'array',
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

    /** Seluruh Sub-CPMK yang disasar pekan ini (termasuk yang utama). */
    public function subCpmkSemua(): BelongsToMany
    {
        return $this->belongsToMany(SubCpmk::class, 'rps_minggu_sub_cpmk', 'rps_minggu_id', 'sub_cpmk_id')
            ->withPivot('urutan')
            ->orderBy('rps_minggu_sub_cpmk.urutan');
    }
}
