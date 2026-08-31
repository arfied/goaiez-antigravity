<?php

declare(strict_types=1);

namespace App\Services\Destinations;

use App\Enums\ReviewDestination;
use App\Models\DestinationClick;
use App\Models\Review;
use App\Support\Tenancy;
use InvalidArgumentException;

/**
 * The only writer of `destination_clicks`.
 *
 * ONE METHOD, AND WHAT IT CANNOT DO IS THE POINT. record() sets `review_id`,
 * `destination` and `clicked_at`. There is nothing else it *could* set, because
 * the table has nowhere to put an outcome — decision 113 says a destination
 * click is never recorded or reported as a review, and this is that decision
 * expressed as a shape rather than as a rule somebody has to remember.
 *
 * WHY THE RULE EXISTS. No platform gives us a completion callback. Google,
 * Facebook and Trustpilot all send somebody away to their own page and tell us
 * nothing about what happened there. A click therefore means "we sent them", and
 * any report reading it as "they reviewed us" is a claim we cannot support — to
 * the owner paying for this, and potentially to a platform asking how our
 * numbers were produced.
 *
 * The one true signal that a review landed is it appearing in review sync or
 * email ingest (`24` §1.4). That is slice I's work, and a different table.
 */
final class DestinationClicks
{
    /**
     * Somebody was sent to a destination.
     *
     * @throws InvalidArgumentException when the review belongs to another tenant.
     */
    public function record(Review $review, ReviewDestination $destination): void
    {
        if ($review->business_id !== Tenancy::idOrFail()) {
            throw new InvalidArgumentException(
                'That review belongs to another tenant. A click is filed against the '
                .'acting business, so this would attribute one business\'s traffic to '
                .'another\'s review.',
            );
        }

        $click = new DestinationClick;

        $click->forceFill([
            'review_id' => $review->id,
            'destination' => $destination,
            'clicked_at' => now(),
        ])->save();
    }
}
