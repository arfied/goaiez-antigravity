<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * `GOAIEZ_PIXEL_MASTER_BUILD` §5.4's `fact_session`, rebuilt from L1 per §8.
 *
 * ⚠️ **A COMPOSITE PRIMARY KEY, ON `L2FactSourceDaily`'S OWN TERMS.** The
 * natural key is (`business_id`, `session_id`) and there is no surrogate, for
 * the same reason `l2_fact_source_daily` has none: a sequence cannot be
 * reproduced by a rebuild. This model **reads**; the write path is
 * [[\App\Services\Warehouse\Replayer]], which deletes the sessions the range
 * contains and inserts, and never calls `save()`.
 *
 * ⛔ **`day` WAS THE MIDDLE COLUMN OF THAT KEY UNTIL 2026-08-22 AND THE OWNER
 * REMOVED IT** (8040, 8041). One row per session, whatever range was replayed —
 * `GOAIEZ_PIXEL_MASTER_BUILD` §5.4's own shape. What stands in its place is
 * **`first_received_at`**, which is not the same column renamed: nothing keys on
 * it and nothing groups by it, and the day a session rolls up to is computed at
 * one place in [[\App\Services\Warehouse\Replayer]] (`SESSION_ROLLUP_DAY`)
 * rather than being a fact of the session's identity.
 *
 * `$primaryKey` is `business_id` only so Eloquent has something to answer
 * with; it is not unique and `find()` is meaningless here.
 *
 * @property int $business_id
 * @property string $session_id
 * @property string|null $anonymous_id
 * @property Carbon $started_at
 * @property Carbon $ended_at
 * @property Carbon $first_received_at
 * @property int $duration_s
 * @property int $active_s
 * @property int $pageviews
 * @property int $conversions
 * @property bool $is_engaged
 * @property bool $is_bot
 * @property bool $is_new
 * @property string $entry_page_path
 * @property string $exit_page_path
 * @property string $source_key
 * @property string $device_type
 * @property string $consent_state
 */
final class L2FactSession extends Model implements TenantScoped
{
    use BelongsToTenant;

    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'l2_fact_session';

    protected $primaryKey = 'business_id';

    protected $keyType = 'int';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
            'first_received_at' => 'immutable_datetime',
            'duration_s' => 'integer',
            'active_s' => 'integer',
            'pageviews' => 'integer',
            'conversions' => 'integer',
            'is_engaged' => 'boolean',
            'is_bot' => 'boolean',
            'is_new' => 'boolean',
        ];
    }
}
