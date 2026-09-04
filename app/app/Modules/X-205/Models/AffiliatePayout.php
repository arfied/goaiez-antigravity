<?php

declare(strict_types=1);

namespace App\Modules\X205\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliatePayout extends Model
{
    protected $table = 'affiliate_payouts';

    protected $guarded = [];

    protected $casts = [
        'amount_cents' => 'integer',
        'money_moved' => 'boolean',
    ];
}
