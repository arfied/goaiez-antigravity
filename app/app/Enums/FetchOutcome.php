<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How one fetch attempt ended (`40` §6.3).
 *
 * The distinction that earns its keep is `Blocked` vs `Empty`. `40` §6.1 makes
 * block detection the trigger for a cool-down before escalation — "most 'blocks'
 * are rate limits that patience fixes for free" — while an empty response is the
 * signal that a page needs rendering. Collapsing the two would either escalate
 * to a proxy against a site that was merely slow, or wait six hours for a page
 * that was never going to have server-side HTML.
 *
 * `Refused` is ours rather than `40`'s, and it is not a failure. It is the
 * gateway declining to fetch on policy: a `guided_only` source, a kill switch, a
 * ceiling below the requested tier, or robots saying no. It is recorded because
 * "we did not look" and "we looked and found nothing" are different facts, and
 * `40` §6.4 turns them into different sentences for the owner.
 */
enum FetchOutcome: string
{
    /** A usable response. */
    case Ok = 'ok';

    /** 403, 429, or a recognisable challenge page. Triggers cool-down. */
    case Blocked = 'blocked';

    /** An interstitial or bot-check rather than the page. */
    case Challenge = 'challenge';

    /** A 200 with nothing useful — usually a page that renders client-side. */
    case Empty = 'empty';

    /** Transport failure, timeout, or an unexpected status. */
    case Error = 'error';

    /**
     * The gateway declined on policy. Never escalates, never cools down —
     * waiting changes nothing about a rule.
     */
    case Refused = 'refused';

    /**
     * Whether this outcome should start a cool-down before any escalation.
     *
     * Refused is deliberately absent: a policy refusal is not a transient
     * condition, and cooling down would imply that trying later might work.
     */
    public function triggersCooldown(): bool
    {
        return $this === self::Blocked || $this === self::Challenge;
    }

    public function isSuccess(): bool
    {
        return $this === self::Ok;
    }
}
