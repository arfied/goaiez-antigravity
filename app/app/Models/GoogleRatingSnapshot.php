<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Carbon\CarbonImmutable;
use Database\Factories\GoogleRatingSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One successful read of a location's own Google rating and review count —
 * `App\Services\Visibility\ReviewLossDetection`'s history, and the only
 * writer.
 *
 * ⚠️ **`review_count` IS NULLABLE AND A NULL IS NOT A ZERO.** {@see CompetitorSnapshot}
 * carries the identical warning for the identical vendor reason — a reader of
 * this column must handle null rather than coalesce it.
 *
 * No `updated_at` — {@see BoostScoreHistory}'s convention for an append-only
 * table: `captured_at` is the datum and this table has no other timestamp.
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $location_id
 * @property ?int $review_count
 * @property ?string $rating
 * @property CarbonImmutable $captured_at
 */
final class GoogleRatingSnapshot extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<GoogleRatingSnapshotFactory> */
    use HasFactory;

    public $timestamps = false;

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
            'review_count' => 'integer',
            'rating' => 'decimal:1',
            'captured_at' => 'immutable_datetime',
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
