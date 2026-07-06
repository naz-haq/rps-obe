<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RpsVersion extends Model
{
    protected $table = 'rps_version';
    protected $guarded = [];

    protected $casts = [
        'versi' => 'integer',
        'tanggal_penyusunan' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }

    public function versiPedoman(): BelongsTo
    {
        return $this->belongsTo(VersiPedoman::class, 'versi_pedoman_id');
    }

    public function minggu(): HasMany
    {
        return $this->hasMany(RpsMinggu::class, 'rps_version_id');
    }

    public function komponenPenilaian(): HasMany
    {
        return $this->hasMany(KomponenPenilaian::class, 'rps_version_id');
    }

    public function approvalLogs(): HasMany
    {
        return $this->hasMany(RpsApprovalLog::class, 'rps_version_id');
    }
}
