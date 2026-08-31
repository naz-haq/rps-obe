<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tautan dokumen rujukan ↔ mata kuliah (kunci natural kode_mk). */
class MkDokumenRujukan extends Model
{
    protected $table = 'mk_dokumen_rujukan';
    protected $guarded = [];

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(DokumenRujukan::class, 'dokumen_rujukan_id');
    }
}
