<?php

declare(strict_types=1);

namespace App\Modules\X170\Models;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    protected $table = 'commissions';

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
        'clawback_amount_cents' => 'integer',
    ];
}
