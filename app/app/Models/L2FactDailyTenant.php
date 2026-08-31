<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * `GOAIEZ_PIXEL_MASTER_BUILD` §5.4's `fact_daily_tenant`, rebuilt from
 * `l2_fact_session` and L1. See that migration's docblock for the reduced
 * column set and why the session-grain facts are read back rather than
 * re-derived.
 *
 * `$primaryKey` is `business_id` only so Eloquent has something to answer
 * with; the real key is (`business_id`, `day`) and `find()` is meaningless
 * here — see [[L2FactSession]] for the same shape. This model **reads**; the
 * write path is [[\App\Services\Warehouse\Replayer]].
 *
 * @property int $business_id
 * @property Carbon $day
 * @property int $sessions
 * @property int $engaged_sessions
 * @property int $bot_sessions
 * @property int $users
 * @property int $new_users
 * @property int $pageviews
 * @property int $conversions
 * @property int $phone_clicks
 * @property int $form_submissions
 * @property int $directions_clicks
 */
final class L2FactDailyTenant extends Model implements TenantScoped
{
    use BelongsToTenant;

    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'l2_fact_daily_tenant';

    protected $primaryKey = 'business_id';

    protected $keyType = 'int';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'immutable_date',
            'sessions' => 'integer',
            'engaged_sessions' => 'integer',
            'bot_sessions' => 'integer',
            'users' => 'integer',
            'new_users' => 'integer',
            'pageviews' => 'integer',
            'conversions' => 'integer',
            'phone_clicks' => 'integer',
            'form_submissions' => 'integer',
            'directions_clicks' => 'integer',
        ];
    }
}
