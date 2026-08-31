<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\ReviewDestination;
use Database\Factories\DestinationClickFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Somebody was sent to a review destination. Never that they left a review.
 *
 * Decision 113: a destination click is never recorded or reported as a review.
 * There is no column here that could hold a completion, and the append-only
 * guards below are what stop one being invented by editing an existing row into
 * a different shape.
 *
 * APPEND-ONLY, ENFORCED RATHER THAN DESCRIBED. `UPDATED_AT = null` stops
 * Eloquent maintaining a timestamp and nothing else. The same reasoning as
 * ConsentRecord (decision 295): a log that can be edited proves nothing, whatever
 * it says. Here the specific danger is narrower and worse — the whole value of
 * this table is that it records *only* what we actually observed, and an UPDATE
 * is how "clicked" becomes "reviewed" without anybody deciding to lie.
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $review_id
 * @property ReviewDestination $destination
 * @property Carbon $clicked_at
 * @property ?Carbon $created_at
 */
final class DestinationClick extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<DestinationClickFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'destination_clicks is append-only. A click is an observation, and '
                .'editing one is how "somebody clicked through" quietly becomes '
                .'"somebody left a review" — which no platform ever tells us.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException(
                'destination_clicks is append-only. Rows are never deleted: a click '
                .'that happened cannot be made not to have happened.'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'destination' => ReviewDestination::class,
            'clicked_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
