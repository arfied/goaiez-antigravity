<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\ReplyStatus;
use App\Services\Export\ExportBuilder;
use Database\Factories\ReplyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A reply to a review (DATA-MODEL §5.3). AI writes replies — never reviews.
 *
 * ⚠️ **EVERY ONE OF THESE IS ANNOTATED FOR ONE REASON: Larastan cannot see a
 * cast declared via the `casts(): array` method.** Without them a `?->` on a
 * Carbon reads as an error on a `string`, a `match ($reply->status)` against
 * `ReplyStatus` analyses against the raw column type, and a
 * `->toIso8601String()` on `$posted_at` does the same — the shape
 * `AutopilotSettings` and `Review` both already carry.
 *
 * `$approved_at`/`$approved_by`/`$text` arrived with the reply queue and
 * `$hold_until`/`$posted_at` with {@see ExportBuilder}, which was the first
 * caller to need two of them on one line. They are one list because they are
 * one problem.
 *
 * @property ReplyStatus $status
 * @property ?Carbon $approved_at
 * @property ?string $approved_by
 * @property string $text
 * @property ?Carbon $hold_until
 * @property ?Carbon $posted_at
 * @property ?Carbon $provider_declined_at
 * @property ?Carbon $publish_unconfirmed_at
 * @property ?Carbon $publish_retry_dispatched_at
 */
final class Reply extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<ReplyFactory> */
    use HasFactory;

    /**
     * ⚠️ AN ALLOWLIST, NOT `$guarded` (1749). This was
     * `$guarded = ['id', 'business_id']`, which made `status`, `approved_at` and
     * `approved_by` mass-assignable — the three columns that record *who
     * authorised text to be published under a business's name on a public
     * listing*. Nothing exploited it: `ReviewReplies` is the only writer and it
     * assigns those as properties. But "nothing currently reaches it" is what
     * `$guarded` bets on every time, and the bet here is an audit trail.
     *
     * `review_id` is the whole list because it is the whole of what this
     * application mass-assigns — `new Reply(['review_id' => $review->id])`, once.
     * Factories are unaffected: `Factory::make()` runs inside `Model::unguarded()`.
     *
     * @var list<string>
     */
    protected $fillable = ['review_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReplyStatus::class,
            'hold_until' => 'datetime',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'provider_declined_at' => 'datetime',
            'publish_unconfirmed_at' => 'datetime',
            'publish_retry_dispatched_at' => 'datetime',
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
