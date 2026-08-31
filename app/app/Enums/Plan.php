<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The plans a tenant can be on — the ladder decisions 154–157 reintroduced.
 *
 * `CLAUDE.md`'s commercial-model table is the authority for what each one costs,
 * and `ArchitectureTest` compares that table against the seed manifest, so this
 * enum names the plans and never carries a price. A price on an enum case is a
 * literal outside the registry, which is the thing doc `38` Part 2's lint exists
 * to refuse.
 *
 * ⚠️ **THERE IS A LADDER AGAIN, AND DECISION 99 SAYS THERE IS NOT.** 95–99 set
 * one plan and stated the tiers were gone; 154–157 reversed that on different
 * terms. A reader who finds 99 alone will conclude this enum should have one
 * case. It should not — read 154–157.
 *
 * ⚠️ **AND THE LADDER IS AN OPEN CONVERSATION AGAIN AS OF 2026-08-11** (2067).
 * The owner was asked whether Free and Limited go away and answered "let's
 * talk", so nothing here moves — but the trial that arrived with that answer
 * (2065) includes every feature and a real SMS balance, which is most of what
 * Free was drawn to do. **Do not delete a case on the strength of that**; the
 * ruling has not been made.
 *
 * `Free` and `Limited` are not degraded versions of `Base`; they are drawn on
 * what the product is *allowed to actuate*. Free is the sensor without the
 * actuator (BUILD-PLAN §5.2) — it watches and reports and writes nothing.
 * Limited is the review engine on email only, and its point is operational
 * rather than commercial: **a Limited tenant needs no 10DLC brand registration
 * at all**, which removes the single slowest step in onboarding.
 */
enum Plan: string
{
    /** The sensor without the actuator. $0 (decisions 154–156). */
    case Free = 'free';

    /**
     * The review engine on email only (decision 157).
     *
     * ⚠️ **ITS PRICE IS NOT SET AND MAY NOT BE GUESSED.** The contents are
     * decided and the number is not (BUILD-PLAN §5.1). `DefaultsManifest` seeds
     * no price row for this plan, and a test asserts the absence, because a
     * plausible figure in a seed manifest becomes policy the first time somebody
     * reads it back out of the database and believes it.
     */
    case Limited = 'limited';

    /** Everything, one location included. $179.99/mo or $997/yr (2053). */
    case Base = 'base';

    /**
     * What an owner is shown. Outcome language (`22`): what they get, not which
     * row of a matrix they are on.
     */
    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Limited => 'Reviews',
            self::Base => 'Everything',
        };
    }
}
