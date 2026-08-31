<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a first-party review reads (`17` FPR-02, `reviews.sentiment`).
 *
 * Three values, not five. A finer scale invites an argument about whether a
 * review is "somewhat negative" that nothing downstream can act on: slice E
 * routes on the customer's own star rating, not on this, and this exists for
 * reporting and for the triage screen's summary.
 *
 * NOT A ROUTING INPUT. Routing reads `rating` and the per-destination threshold
 * (decision 111). A model disagreeing with a customer's own stars must never
 * change where that customer is sent.
 */
enum ReviewSentiment: string
{
    case Positive = 'positive';
    case Neutral = 'neutral';
    case Negative = 'negative';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
