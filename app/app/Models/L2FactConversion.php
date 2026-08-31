<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * `GOAIEZ_PIXEL_MASTER_BUILD` §5.4's `fact_conversion`, rebuilt from L1 per
 * §8's attribution rule.
 *
 * ⛔ **`attributed_source_key` AND `attributed_at` ARE FROZEN.** §8: "Resolve
 * at conversion time, freeze on the row, never recompute." This model has no
 * writer besides [[\App\Services\Warehouse\Replayer]] — a rebuild recomputes
 * the whole row from scratch (it deletes the range first), but nothing may
 * ever `UPDATE` `attributed_source_key` on a row that survives untouched.
 *
 * `$primaryKey` is `business_id` only so Eloquent has something to answer
 * with; the real key is (`business_id`, `conversion_id`) and `find()` is
 * meaningless here — see [[L2FactSession]] for the same shape.
 *
 * @property int $business_id
 * @property Carbon $day
 * @property string $conversion_id
 * @property string|null $session_id
 * @property string|null $anonymous_id
 * @property string $conversion_type
 * @property Carbon $occurred_at
 * @property string $attributed_source_key
 * @property Carbon $attributed_at
 * @property int $touch_count
 * @property int $days_to_convert
 * @property string $page_path
 */
final class L2FactConversion extends Model implements TenantScoped
{
    use BelongsToTenant;

    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'l2_fact_conversion';

    protected $primaryKey = 'business_id';

    protected $keyType = 'int';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'immutable_date',
            'occurred_at' => 'immutable_datetime',
            'attributed_at' => 'immutable_datetime',
            'touch_count' => 'integer',
            'days_to_convert' => 'integer',
        ];
    }
}
