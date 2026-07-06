<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cpl extends Model
{
    protected $table = 'cpl';
    protected $guarded = [];

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class, 'kurikulum_id');
    }

    public function target(): HasMany
    {
        return $this->hasMany(TargetCpl::class, 'cpl_id');
    }

    public function profilLulusan(): BelongsToMany
    {
        return $this->belongsToMany(ProfilLulusan::class, 'pl_cpl', 'cpl_id', 'profil_lulusan_id')
            ->withTimestamps();
    }

    public function cpmk(): BelongsToMany
    {
        return $this->belongsToMany(Cpmk::class, 'cpmk_cpl', 'cpl_id', 'cpmk_id')
            ->withPivot('bobot')
            ->withTimestamps();
    }
}
