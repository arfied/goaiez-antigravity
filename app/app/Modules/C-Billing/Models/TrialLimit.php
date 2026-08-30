<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Models;

use Illuminate\Database\Eloquent\Model;

class TrialLimit extends Model
{
    protected $table = 'trial_limits';

    protected $guarded = [];

    protected $casts = [
        'included_minutes' => 'integer',
        'rate_cents_per_min' => 'integer',
        'daily_topup_ceiling_cents' => 'integer',
        'topups_today_cents' => 'integer',
        'current_balance_hundredths_cents' => 'integer',
        'last_topup_date' => 'date',
    ];
}
