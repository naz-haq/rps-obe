<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapaianMahasiswa extends Model
{
    protected $table = 'capaian_mahasiswa';
    protected $guarded = [];

    protected $casts = [
        'jumlah_mahasiswa' => 'integer',
        'nilai_rata_rata' => 'decimal:2',
        'persentase_capaian_minimal' => 'decimal:2',
    ];

    public function institusi(): BelongsTo
    {
        return $this->belongsTo(Institusi::class, 'institusi_id');
    }

    public function subCpmk(): BelongsTo
    {
        return $this->belongsTo(SubCpmk::class, 'sub_cpmk_id');
    }

    public function cpmk(): BelongsTo
    {
        return $this->belongsTo(Cpmk::class, 'cpmk_id');
    }
}
