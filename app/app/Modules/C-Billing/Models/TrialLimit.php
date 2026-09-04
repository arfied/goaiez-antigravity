<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $business_id
 * @property int $included_minutes
 * @property int $rate_cents_per_min
 * @property int $daily_topup_ceiling_cents
 * @property int $topups_today_cents
 * @property int $current_balance_hundredths_cents
 * @property ?CarbonInterface $last_topup_date
 */
class TrialLimit extends Model implements TenantScoped
{
    use BelongsToTenant;

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
