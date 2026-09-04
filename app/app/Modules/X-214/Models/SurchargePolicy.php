<?php

declare(strict_types=1);

namespace App\Modules\X214\Models;

use Illuminate\Database\Eloquent\Model;

class SurchargePolicy extends Model
{
    protected $table = 'surcharge_policies';

    protected $guarded = [];

    protected $casts = [
        'merchant_effective_rate_basis_points' => 'integer',
        'is_enabled' => 'boolean',
    ];
}
