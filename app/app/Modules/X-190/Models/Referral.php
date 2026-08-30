<?php

declare(strict_types=1);

namespace App\Modules\X190\Models;

use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    protected $table = 'referrals';

    protected $guarded = [];

    protected $casts = [
        'package_delivered' => 'boolean',
        'partner_bought' => 'boolean',
    ];
}
