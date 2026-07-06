<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpsApprovalLog extends Model
{
    protected $table = 'rps_approval_log';
    protected $guarded = [];

    public function rpsVersion(): BelongsTo
    {
        return $this->belongsTo(RpsVersion::class, 'rps_version_id');
    }
}
