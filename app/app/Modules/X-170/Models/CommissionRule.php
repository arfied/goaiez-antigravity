<?php

declare(strict_types=1);

namespace App\Modules\X170\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionRule extends Model
{
    protected $table = 'commission_rules';

    protected $guarded = [];

    protected $casts = [
        'percentage' => 'float',
        'threshold_cents' => 'integer',
        'is_active' => 'boolean',
    ];
}
