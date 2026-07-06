<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlCpl extends Model
{
    protected $table = 'pl_cpl';
    protected $guarded = [];

    public function profilLulusan(): BelongsTo
    {
        return $this->belongsTo(ProfilLulusan::class, 'profil_lulusan_id');
    }

    public function cpl(): BelongsTo
    {
        return $this->belongsTo(Cpl::class, 'cpl_id');
    }
}
