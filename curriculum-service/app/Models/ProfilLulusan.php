<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProfilLulusan extends Model
{
    protected $table = 'profil_lulusan';
    protected $guarded = [];

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class, 'kurikulum_id');
    }

    public function cpl(): BelongsToMany
    {
        return $this->belongsToMany(Cpl::class, 'pl_cpl', 'profil_lulusan_id', 'cpl_id')
            ->withTimestamps();
    }
}
