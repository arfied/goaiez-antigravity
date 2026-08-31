<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * §11 row 4's counter — one row per business per calendar month.
 *
 * ⚠️ **READ-MOSTLY FROM HERE.** The increments are an atomic `INSERT … ON
 * CONFLICT` written by `App\Services\Pixel\MonthlyEventCap` directly, not an
 * Eloquent `increment()` call — two concurrent requests both reading, both
 * adding one, and both saving is decision 350's shape, and the unique index
 * on (`business_id`, `month`) is what an upsert can use safely where a
 * read-then-write cannot. This model exists for the tenant-scoped read side:
 * an Ops or account screen asking "how close is this tenant to its cap".
 *
 * @property-read int $id
 * @property int $business_id
 * @property Carbon $month
 * @property int $events_total
 * @property int $events_dropped
 */
final class PixelMonthlyUsage extends Model implements TenantScoped
{
    use BelongsToTenant;

    /**
     * ⛔ **NOT `pixel_monthly_usages`, AND THIS LINE IS LOAD-BEARING.** "Usage"
     * is a mass noun, so the table is singular; Laravel's pluraliser is not,
     * and without this the model reads and writes a table that does not exist.
     * ⚠️ **Nothing in this lane's own tests would have caught it on their own**
     * — they drive `MonthlyEventCap`, which *writes* through `DB::table()` with
     * a literal name, so the write lands and only the read throws.
     * `TenancyTest`'s row-level-security lint is what found it, by deriving a
     * table name from the model and finding no such table with a policy on it.
     *
     * @var string
     */
    protected $table = 'pixel_monthly_usage';

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'date',
            'events_total' => 'integer',
            'events_dropped' => 'integer',
        ];
    }
}
