<?php

declare(strict_types=1);

namespace App\Services\Billing;

/**
 * How many SMS credits a send costs — the **retail** unit, and the owner's rule
 * of 2026-08-30 (decision 12461).
 *
 *     credits = ceil(characters / 160) + number_of_photos
 *
 * Counted **by characters, uniformly.** ⛔ **THERE IS NO ENCODING BRANCH AND
 * THAT IS THE RULING RATHER THAN A SIMPLIFICATION.** The owner was asked
 * directly whether an emoji should cost more because the carrier charges more
 * for it, and answered: *"Bill emojis and the other thing based on characters —
 * if they use more than 160 it's another credit. Yes, track everything by
 * characters."*
 *
 * ## ⛔ THIS IS NOT `SmsSegments` AND MUST NEVER BECOME IT
 *
 * That class estimates what the **carrier** bills us: 160 for a single segment
 * and **153** per part once a message splits. This one is what the **tenant**
 * pays: 160 throughout, with no split rule at all. **They disagree on purpose**,
 * and `CostBookWritersTest` asserts they disagree on a real body rather than
 * trusting the two files to stay apart — because the tidy-minded edit is to
 * notice two `160`s and call one of them a duplicate.
 *
 * ⚠️ **THE DIFFERENCE IS MARGIN WE KNOWINGLY ABSORB** (12462), in two places and
 * both favouring the tenant:
 *
 *   **emoji and accents** — one emoji drops a carrier segment from 160
 *   characters to **70**, so a 100-character message with one emoji is two
 *   segments at the carrier and **one** credit here.
 *
 *   **long messages** — carriers bill 153 per part once a message splits, so a
 *   320-character text is three segments at the carrier and **two** credits
 *   here.
 *
 * ⛔ **NEITHER IS A DEFECT TO FIX.** The owner was shown the first and chose the
 * simpler rule; a lane "correcting" this class toward the carrier's arithmetic
 * would be reversing a ruling, and the reversal would read on a diff as removing
 * an inconsistency.
 *
 * ## What a character is here, said plainly
 *
 * ⚠️ **`mb_strlen()`, so a character is a UTF-8 CODE POINT.** `é` is one and a
 * plain emoji is one, which is what the owner's *"an emoji is one character"*
 * asks for. ⛔ **A ZWJ sequence is not one**: a family emoji is five code points
 * and is billed as five characters. **`grapheme_strlen()` would be the
 * user-perceived count and `ext-intl` IS NOT LOADED ON THIS SERVER** — measured,
 * not assumed — so adopting it is a dependency decision and an owner ask, not a
 * quiet swap. The residual is stated here rather than left for somebody to
 * discover in a dispute.
 *
 * ⚠️ **`SmsSegments` IS NAMED IN BACKTICKS AND NEVER WITH A `{@see}`, FOR ITS
 * OWN STATED REASON.** Pint's `fully_qualified_strict_types` promotes a `{@see}`
 * into a real `use` statement, and a billing class importing the carrier
 * estimator is the first half of the fusion this docblock exists to refuse —
 * **measured here rather than reasoned about: Pint rewrote it on the first
 * run.**
 *
 * ⚠️ **160 IS THE OWNER'S SECOND ANSWER.** He first said 159; asked to confirm,
 * he moved to 160. **159 is `ReviewAskCatalog::CEILING`, a copy-writing budget
 * with headroom for a link** — a writing guideline and never a billing unit, and
 * the two must not be re-fused.
 */
final class SmsCreditUnits
{
    /**
     * The retail credit unit, in characters.
     *
     * ⚠️ **A constant rather than a literal at the one site that uses it**, so
     * that the failure message of any test that moves it names the rule instead
     * of a number, and so that a reader grepping for the owner's 160 lands here
     * rather than in `SmsSegments`.
     */
    public const CHARACTERS_PER_CREDIT = 160;

    /**
     * What this send costs the tenant, in SMS credits.
     *
     * ⛔ **NEVER BELOW ONE.** A body that is null or empty with no media would
     * otherwise arithmetically cost nothing, and a free send is a claim this
     * application must not make — the same reason
     * `SmsSegments::count()` floors at one.
     * ⚠️ **The floor is unreachable on the real path** and is not load-bearing
     * there: `OutboundMessage::for()` throws on an empty body, pinned by
     * `SendContractTest`'s *"an outbound message refuses an empty body"*. It
     * exists for the row this method is actually handed — see
     * {@see SendCredits::creditsFor()} on the null arm.
     *
     * @param  string|null  $body  The exact words that were sent, off the row.
     * @param  int  $mediaCount  How many photos rode with them.
     */
    public static function forSend(?string $body, int $mediaCount): int
    {
        $characters = mb_strlen((string) $body);

        $forText = (int) ceil($characters / self::CHARACTERS_PER_CREDIT);

        return max(1, $forText + max(0, $mediaCount));
    }
}
