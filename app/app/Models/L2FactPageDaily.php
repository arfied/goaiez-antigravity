<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * `GOAIEZ_PIXEL_MASTER_BUILD` §5.4's `fact_page_daily`, rebuilt from
 * `l2_fact_session` and L1. See that migration's docblock for the reduced
 * column set.
 *
 * `$primaryKey` is `business_id` only so Eloquent has something to answer
 * with; the real key is (`business_id`, `day`, `page_path`) and `find()` is
 * meaningless here — see [[L2FactSession]] for the same shape. This model
 * **reads**; the write path is [[\App\Services\Warehouse\Replayer]].
 *
 * @property int $business_id
 * @property Carbon $day
 * @property string $page_path
 * @property int $pageviews
 * @property int $unique_views
 * @property int $exits
 * @property int $conversions
 * @property int $js_errors
 */
final class L2FactPageDaily extends Model implements TenantScoped
{
    use BelongsToTenant;

    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'l2_fact_page_daily';

    protected $primaryKey = 'business_id';

    protected $keyType = 'int';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'immutable_date',
            'pageviews' => 'integer',
            'unique_views' => 'integer',
            'exits' => 'integer',
            'conversions' => 'integer',
            'js_errors' => 'integer',
        ];
    }
}
