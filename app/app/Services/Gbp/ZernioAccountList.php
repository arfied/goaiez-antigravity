<?php

declare(strict_types=1);

namespace App\Services\Gbp;

/**
 * Every account connected under our Zernio platform key, in one answer.
 *
 * {@see GbpReviewPage}'s shape — the payload plus a count of what could not be
 * read — and for the same reason. A vendor envelope that moves must not turn
 * into a smaller number that looks like good news: here the number in question
 * is *how many accounts we are being billed for*, and an entry quietly dropped
 * for being unparseable is an account that is paid for and invisible, which is
 * the whole failure this reconciliation exists to close (3297).
 */
final readonly class ZernioAccountList
{
    /**
     * @param  list<ZernioAccount>  $accounts
     */
    public function __construct(
        public array $accounts,
        /**
         * Entries in the vendor's list that carried no readable account id.
         *
         * ⚠️ **NOT AN ERROR AND NOT ZERO BY ASSUMPTION.** Zernio bills per
         * connected account, so one of these is still a line on the invoice —
         * it simply cannot be matched against anything of ours. The report
         * carries the count so the totals add up out loud.
         */
        public int $unreadable,
    ) {}

    /**
     * How many accounts the vendor says are connected, readable or not.
     */
    public function billedCount(): int
    {
        return count($this->accounts) + $this->unreadable;
    }
}
