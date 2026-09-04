<?php

declare(strict_types=1);

namespace App\Modules\X165\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipPlan extends Model
{
    protected $table = 'membership_plans';

    protected $guarded = [];

    protected $casts = [
        'price_cents' => 'integer',
        'billing_interval_months' => 'integer',
        'renewal_reminder_days' => 'integer',
    ];
}
