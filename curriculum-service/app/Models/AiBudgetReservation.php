<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiBudgetReservation extends Model
{
    protected $table = 'ai_budget_reservations';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:6',
        'expires_at' => 'datetime',
    ];
}
