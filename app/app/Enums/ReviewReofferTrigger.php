<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why `ReviewRouter::reoffer()` is being asked to re-evaluate a review's
 * invite — wave 38 lane C (10590–10609).
 *
 * ⚠️ **NAMED IN PROSE RATHER THAN WITH `{@see}`** — Pint's
 * `fully_qualified_strict_types` turns a `{@see}` into a real `use` import,
 * and an enum importing a service is a dependency nobody chose
 * (`ReviewInviteSender`'s own recorded reason, 2908).
 *
 * ⚠️ **THIS IS THE GENERALISATION `ReinviteDeferredReviews.php`'s DOCBLOCK
 * WARNED THE NEXT LANE ABOUT, AND IT IS A PARAMETER RATHER THAN A REWRITE.**
 * `reoffer()` existed for exactly one caller — a pause resuming — and gated on
 * `invite_deferred_at !== null`. A second caller needs the *same* recompute
 * (destinations, thresholds, the pause/suspension re-check, the audit trail)
 * for a review that was never deferred at all: a below-threshold rating whose
 * customer has since told this platform, in their own words, that the thing
 * they complained about is fixed. **A default parameter means the pause path
 * is untouched — `ReinviteDeferredReviews` calls `reoffer($review)` exactly as
 * it always has, and every existing test for it is unmoved.**
 *
 * ⚠️ **SAY WHAT THE NEW ARM ADMITS THAT THE OLD ONE DID NOT, BECAUSE A
 * GENERALISED GATE IS EXACTLY WHERE ONE WIDENS SILENTLY.** {@see self::PauseResumed}
 * re-evaluates the *same* threshold test the review was always going to face —
 * a rating that was invitable before the pause is invitable after it, nothing
 * more. {@see self::FixThenAskConfirmed} is different in kind: the review's
 * `rating` never changes, so re-running the ordinary threshold test would find
 * exactly the same `false` it found the day the review was left, and this
 * trigger exists *because* that answer is now stale. A customer's star rating
 * from the day of the complaint is a weaker, older signal than their own
 * explicit, dated, one-question confirmation that the thing they complained
 * about was fixed — so this arm skips the rating comparison and offers every
 * destination the tenant has otherwise enabled. **Every other constraint is
 * unmoved**: `send_review_requests` still empties the list, Trustpilot is
 * still forced to a threshold of `0` and Yelp is still reachable only through
 * the confirmed-listing path (both decided in `DestinationSettings`, above
 * `snapshot()`), the Google place-id undeliverable check still applies, and
 * the pause/suspension re-check still refuses a tenant currently stopped.
 * **The only thing this trigger admits that `PauseResumed` does not is a
 * review whose original star rating is below every destination's
 * `invite_threshold`.**
 */
enum ReviewReofferTrigger
{
    /**
     * A tenant pause emptied the snapshot and this review's invite half was
     * never fairly evaluated — decision 822's other half.
     */
    case PauseResumed;

    /**
     * The customer confirmed, through their own click on the fix-then-ask
     * check-in, that the thing they complained about was fixed.
     */
    case FixThenAskConfirmed;
}
