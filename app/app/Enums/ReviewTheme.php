<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a review was about (`17` FPR-02, `reviews.themes`).
 *
 * A CLOSED VOCABULARY, AND THAT IS THE DECISION. Free-form themes make
 * "slow service", "service was slow" and "waiting" three buckets, so any themes
 * report needs a clustering step that does not exist, and nothing validates what
 * is stored. A closed set aggregates from the first review and cannot be split
 * by a synonym the model invented.
 *
 * THE COST IS REAL AND IS STATED RATHER THAN HIDDEN: a theme genuinely outside
 * this set becomes `Other` and is invisible until somebody widens the enum.
 * Widening is one case and a deploy — the column stores strings, so there is no
 * migration and no `ALTER TYPE`.
 *
 * INDUSTRY-NEUTRAL ON PURPOSE. `29` §6.1 sells to trades, dental, medical,
 * hospitality and retail alike. A vocabulary carrying "bedside manner" would be
 * dead weight for a plumber, and one carrying "punctuality" would be read as
 * lateness by a restaurant. These nine are the intersection.
 */
enum ReviewTheme: string
{
    case Service = 'service';
    case Staff = 'staff';
    case WaitTime = 'wait_time';
    case Price = 'price';
    case Cleanliness = 'cleanliness';
    case Quality = 'quality';
    case Booking = 'booking';
    case Communication = 'communication';
    case Facilities = 'facilities';

    /** Something real was said that this vocabulary cannot name. */
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * A value a model produced, made safe to store.
     *
     * Falls back to Other rather than dropping. Structured output constrains the
     * response to this vocabulary already, so this is the belt to that braces —
     * and the failure it guards is a model returning a near-miss under a future
     * provider whose schema support is weaker, where losing the fact that
     * *something* was said is worse than losing which thing.
     */
    public static function fromModel(string $value): self
    {
        return self::tryFrom($value) ?? self::Other;
    }
}
