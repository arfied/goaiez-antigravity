<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Contracts\FetchGateway;
use App\Services\Fetch\FetchResult;

/**
 * What asking *"does this location's About page answer right now"* found —
 * including the case where we were not allowed to ask.
 *
 * ⛔ **"WE DID NOT LOOK" AND "WE LOOKED AND FOUND NOTHING" ARE TWO ANSWERS AND
 * THE FETCH LAYER ALREADY KEPT THEM APART** ({@see FetchResult::wasRefused()},
 * whose own docblock says so, and `40` §6.4, which requires every owner surface
 * to preserve the distinction). {@see AuthorByline::verify()} collapsed both
 * into one null, one line after the gateway had gone to the trouble of making
 * the difference — so a tenant whose own `robots.txt` disallows us was told
 * their About page had stopped working, and asked to give us one they had
 * already given us.
 *
 * ⛔ **THIS TYPE CHANGES NO GATE AND MUST NOT.** 5729's rule survives every
 * overrule: *whatever name carries the byline, it links somewhere or the page
 * does not publish*. {@see self::$byline} is null for a refusal exactly as it is
 * for a 404, and {@see AuthorByline::verify()} still returns it unchanged. What
 * is added is a **fact about why**, for the sentence somebody is told — decision
 * 6065, and the open half of 5744.
 *
 * ⚠️ **THE OPEN HALF IS THE OWNER'S AND IS NOT DECIDED HERE** (5744, 6066).
 * Whether an owner-confirmed, credentialled site should be fetched **anyway**,
 * over its own robots directive, is a ruling about somebody else's property.
 * What this class does is make the refusal say what it is, which needs nobody's
 * permission and is true whichever way that ruling goes.
 */
final readonly class BylineCheck
{
    private function __construct(
        public ?PageByline $byline,
        /**
         * Whether this platform was permitted to fetch the page at all.
         *
         * ⚠️ **TRUE WHENEVER THE QUESTION DOES NOT ARISE**, including when there
         * is no About page to fetch and when the page answered perfectly. It is
         * a statement about the **fetch policy**, not about the page: false means
         * exactly *"the gateway declined to open a socket"*, which today is
         * robots, a spent rate budget, a kill switch or a guided-only source.
         */
        public bool $reachable,
        /**
         * Whether the tenant's **own** `robots.txt` is what stopped us.
         *
         * ⛔ **THE SENTENCE AN OWNER READS DEPENDS ON THIS AND MUST**, because
         * a refusal has several causes and only one of them is theirs to fix. A
         * spent rate budget and a killed source are ours and clear themselves;
         * telling a customer their website was blocking us when in fact we had
         * run out of fetches for the hour would send them to edit a file that
         * was never the problem. 5744's population is this flag exactly.
         */
        public bool $ownRobotsRefused = false,
    ) {}

    public static function verified(PageByline $byline): self
    {
        return new self($byline, true);
    }

    /**
     * Nothing to sign with, or a page that answered badly. **We looked.**
     */
    public static function missing(): self
    {
        return new self(null, true);
    }

    /**
     * ⛔ **THE GATEWAY DECLINED ON POLICY** ({@see FetchGateway}) — no socket was
     * opened, so nothing is known about the page one way or the other. On the
     * tenant's own confirmed website the overwhelmingly likely cause is their own
     * `robots.txt`, which is the population 5744 describes: *"a hardened
     * small-business site with a blanket `Disallow: /` for unknown agents is
     * ordinary"*.
     */
    public static function notLookedAt(bool $ownRobotsRefused = false): self
    {
        return new self(null, false, $ownRobotsRefused);
    }
}
