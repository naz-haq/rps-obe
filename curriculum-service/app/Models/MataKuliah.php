<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MataKuliah extends Model
{
    protected $table = 'mata_kuliah';
    protected $guarded = [];

    protected $casts = [
        'sks_teori' => 'integer',
        'sks_praktik' => 'integer',
        'semester' => 'integer',
        'jumlah_minggu' => 'integer',
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

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class, 'kurikulum_id');
    }

    public function pengampu(): HasMany
    {
        return $this->hasMany(MkPengampu::class, 'kode_mk', 'kode_mk');
    }

    public function getSksAttribute(): int
    {
        return (int) $this->sks_teori + (int) $this->sks_praktik;
    }
}
