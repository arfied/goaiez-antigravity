<?php

declare(strict_types=1);

namespace App\Modules\X199\Models;

use Illuminate\Database\Eloquent\Model;

class OverflowCharge extends Model
{
    protected $table = 'overflow_charges';

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
    ];
}
