<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * `GOAIEZ_PIXEL_MASTER_BUILD` §5.4's `fact_source_daily`, rebuilt from L1.
 *
 * ⚠️ **A COMPOSITE PRIMARY KEY, WHICH ELOQUENT DOES NOT MODEL.** The natural key
 * is (`business_id`, `day`, `utm_source`, `utm_medium`) and there is no
 * surrogate, because a surrogate would either be a sequence — which a rebuild
 * cannot reproduce — or a hash of the four columns, which is a second thing to
 * keep correct for no gain. So this model **reads**; the write path is
 * [[\App\Services\Warehouse\Replayer]], which deletes the range and inserts, and
 * never calls `save()`.
 *
 * `$primaryKey` is set to `business_id` only so Eloquent has something to answer
 * with; **it is not unique and must not be used to fetch a row**. `find()` on
 * this model is meaningless and a query builder call with the four dimensions is
 * the only correct read.
 *
 * ⚠️ **THE FACTS EXCLUDE BOTS AND `bot_events` IS NOT A SUBSET OF `events`.**
 * §5.4: *"All L2 tables exclude bot traffic from tenant-facing metrics (bot
 * counts are reported separately)."* A report that adds them together is
 * double-counting nothing and inventing traffic.
 *
 * @property int $business_id
 * @property Carbon $day
 * @property string $utm_source
 * @property string $utm_medium
 * @property int $events
 * @property int $sessions
 * @property int $visitors
 * @property int $bot_events
 */
final class L2FactSourceDaily extends Model implements TenantScoped
{
    use BelongsToTenant;

    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'l2_fact_source_daily';

    protected $primaryKey = 'business_id';

    protected $keyType = 'int';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'immutable_date',
            'events' => 'integer',
            'sessions' => 'integer',
            'visitors' => 'integer',
            'bot_events' => 'integer',
        ];
    }
}
