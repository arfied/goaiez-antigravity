<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How much of a business's inbound calling the platform touches (DATA-MODEL §5.9).
 *
 * Starts at TrackingOnly and never advances by itself. Everything above it
 * intercepts a real customer call, and `29` §2 rule 26 is explicit that we never
 * replace their line — a misconfiguration here is a business that stops
 * receiving calls, which is worse than any feature it enables.
 */
enum CallRoutingMode: string
{
    /** Measure only. Their line rings exactly as it did before. */
    case TrackingOnly = 'tracking_only';

    /** We answer only when they do not — ring timeout must beat carrier voicemail. */
    case Conditional = 'conditional';

    /** Calls route through us first. Highest capability, highest blast radius. */
    case Proxy = 'proxy';

    public function label(): string
    {
        return match ($this) {
            self::TrackingOnly => 'Measure only',
            self::Conditional => 'Answer missed calls',
            self::Proxy => 'Answer all calls',
        };
    }

    /**
     * The sentence under the label on `Account\Calls`.
     *
     * Here rather than in the template, beside `label()`, for the reason
     * `AutopilotActionType::title()` gives: the vocabulary stays closed and one
     * test can assert all of it. Outcome language (`22`) — every line says what
     * happens to the business's phone, never what routes where.
     *
     * ⚠️ **`Proxy` NAMES ITS COST IN ITS OWN DESCRIPTION.** `26` §3.2 states it
     * plainly — *"their phone now depends on our uptime"* — and it is the one
     * fact an owner cannot discover afterwards without an outage. The other two
     * carry no such warning, which is deliberate: a caution attached to every
     * option is one nobody reads.
     */
    public function description(): string
    {
        return match ($this) {
            self::TrackingOnly => 'Your phone rings exactly as it does today. We measure what we can '
                .'and change nothing.',
            self::Conditional => 'Your phone rings first. If nobody picks up, or the line is busy, '
                .'the call comes to us — we take a message, text the caller back and tell you who '
                .'rang.',
            self::Proxy => 'Every call comes to us first and we ring you straight away. You get the '
                .'fullest picture of who calls you, and your phone depends on us being up.',
        };
    }

    /**
     * Whether this mode can intercept a call the business would otherwise take.
     */
    public function touchesLiveCalls(): bool
    {
        return $this !== self::TrackingOnly;
    }
}
