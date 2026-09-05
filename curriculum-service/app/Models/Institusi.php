<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institusi extends Model
{
    protected $table = 'institusi';
    protected $guarded = [];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** ID institusi ini beserta seluruh unit turunannya (fakultas → prodi). */
    public static function idsDalamSubtree(int $rootId): array
    {
        $anakPerInduk = self::query()->select('id', 'parent_id')->get()->groupBy('parent_id');
        $ids = [$rootId];
        $tumpukan = [$rootId];
        while ($tumpukan !== []) {
            foreach ($anakPerInduk->get(array_pop($tumpukan), collect()) as $anak) {
                $ids[] = (int) $anak->id;
                $tumpukan[] = (int) $anak->id;
            }
        }

        return $ids;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'institusi_id');
    }

    public function mataKuliah(): HasMany
    {
        return $this->hasMany(MataKuliah::class, 'institusi_id');
    }

    public function vmts(): HasMany
    {
        return $this->hasMany(ProdiVmts::class, 'institusi_id');
    }
}
