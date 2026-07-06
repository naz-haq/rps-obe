<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Kurikulum extends Model
{
    protected $table = 'kurikulum';
    protected $guarded = [];

    protected $casts = [
        'tanggal_berlaku' => 'date',
        'tanggal_pensiun' => 'date',
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

    public function mengganti(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class, 'mengganti_id');
    }

    public function mataKuliah(): HasMany
    {
        return $this->hasMany(MataKuliah::class, 'kurikulum_id');
    }

    public function profilLulusan(): HasMany
    {
        return $this->hasMany(ProfilLulusan::class, 'kurikulum_id');
    }

    public function cpl(): HasMany
    {
        return $this->hasMany(Cpl::class, 'kurikulum_id');
    }

    public function bahanKajian(): HasMany
    {
        return $this->hasMany(BahanKajian::class, 'kurikulum_id');
    }
}
