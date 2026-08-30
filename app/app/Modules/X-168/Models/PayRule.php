<?php

declare(strict_types=1);

namespace App\Modules\X168\Models;

use Illuminate\Database\Eloquent\Model;

class PayRule extends Model
{
    protected $table = 'pay_rules';

    protected $guarded = [];

    protected $casts = [
        'standard_hours_per_week' => 'float',
        'is_active' => 'boolean',
    ];
}
