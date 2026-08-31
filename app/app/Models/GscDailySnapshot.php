<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Database\Factories\GscDailySnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One day of Google Search performance for one location.
 *
 * ⚠️ **`is_final` IS NOT DECORATION.** Google's most recent days are still being
 * counted, and a row whose `is_final` is false is a number that will change. The
 * reading layer refuses to present an unfinalised window as settled, which is
 * what keeps decision 1084's rule true at the far end of the pipe rather than
 * only at the vendor boundary.
 *
 * ⚠️ **`date` IS GOOGLE'S PACIFIC-TIME DAY**, not ours and not the tenant's. Cast
 * to a plain date for that reason: there is no time of day here to be wrong
 * about, and a `datetime` cast would invite a timezone conversion that shifts
 * every row.
 *
 * ⚠️ **`App\Services\Visibility\VisibilityReadings` is the only thing in `app/`
 * allowed to read or write this**, held there by a lint. The reason is decision
 * 620's inversion: a second reader that skips the `is_final` check reports a
 * moving number as a finished one, and nothing about that query would look wrong
 * on its own diff.
 */
final class GscDailySnapshot extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<GscDailySnapshotFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clicks' => 'integer',
            'impressions' => 'integer',
            'position' => 'float',
            'is_final' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
