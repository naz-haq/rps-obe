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

    /**
     * ID institusi ini beserta seluruh LELUHURnya (prodi → fakultas → universitas),
     * urut dari paling spesifik ke paling umum. Dipakai resolusi aturan yang
     * mewaris ke bawah: aturan universitas berlaku untuk fakultas/prodi di
     * bawahnya bila belum ditimpa.
     *
     * @return list<int>
     */
    public static function idsHierarkiKeAtas(int $id): array
    {
        $indukPerId = self::query()->select('id', 'parent_id')->get()->keyBy('id');
        $ids = [];
        $cursor = $id;
        $aman = 0;
        while ($cursor !== null && ! in_array($cursor, $ids, true) && $aman++ < 20) {
            $ids[] = (int) $cursor;
            $cursor = $indukPerId->get($cursor)?->parent_id;
            $cursor = $cursor !== null ? (int) $cursor : null;
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
