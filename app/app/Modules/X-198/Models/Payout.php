<?php

declare(strict_types=1);

namespace App\Modules\X198\Models;

use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    protected $table = 'payouts';

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
        'payout_date' => 'date',
    ];
}
