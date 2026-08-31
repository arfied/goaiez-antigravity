<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Services\Places\PlaceSummary;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One capture of a competitor's review count and rating.
 *
 * ⚠️ **BOTH NUMBERS ARE NULLABLE AND A NULL IS NOT A ZERO.** Google omits a
 * field holding its default value even when the field mask asked for it, so
 * "no rating" and "no count" arrive looking exactly like "no data" — see
 * {@see PlaceSummary::knownReviewCount()}, which is the
 * only thing allowed to decide which of the two a response means. A reader of
 * either column must handle null rather than coalesce it: a `?? 0` here is the
 * defect that made this column nullable in the first place.
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $competitor_id
 * @property ?int $review_count
 * @property ?string $rating
 * @property CarbonImmutable $captured_at
 */
class CompetitorSnapshot extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @var list<string> */
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
     * @return BelongsTo<Competitor, $this>
     */
    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Competitor::class);
    }
}
