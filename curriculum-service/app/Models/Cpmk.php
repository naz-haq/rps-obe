<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cpmk extends Model
{
    protected $table = 'cpmk';
    protected $guarded = [];

    protected $casts = [
        'bobot_persen' => 'decimal:2',
        'taksonomi_kode' => 'array',
    ];

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }

    public function taksonomi(): BelongsTo
    {
        return $this->belongsTo(Taksonomi::class, 'taksonomi_id');
    }

    public function cpl(): BelongsToMany
    {
        return $this->belongsToMany(Cpl::class, 'cpmk_cpl', 'cpmk_id', 'cpl_id')
            ->withPivot('bobot')
            ->withTimestamps();
    }

    public function subCpmk(): HasMany
    {
        return $this->hasMany(SubCpmk::class, 'cpmk_id');
    }
}
