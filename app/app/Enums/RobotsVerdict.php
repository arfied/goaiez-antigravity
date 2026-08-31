<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Fetch\RobotsPolicy;

/**
 * What asking a stranger's `robots.txt` about one URL actually established.
 *
 * ⛔ **THREE OUTCOMES, BECAUSE A `bool` CARRIES TWO AND THE THIRD IS THE ONE
 * SOMEBODY IS TOLD A FALSE SENTENCE ABOUT** (9480–9499). *"We read their rules
 * and they say no"* and *"we could not obtain their rules to read"* are
 * different facts about different people, and {@see RobotsPolicy} returned one
 * `false` for both from the day it shipped. `CLAUDE.md` states the rule for a
 * webhook verifier that fetches its key over the network (9380–9394); this is
 * the same class of fault in a class nobody applied it to, and the population is
 * larger, because the address here belongs to a stranger rather than to a vendor
 * we chose.
 *
 * ⚠️ **ALL THREE STILL FAIL CLOSED AT THE GATEWAY, AND THAT IS NOT WHAT MOVED.**
 * {@see RobotsPolicy}'s own docblock — *"anything unparsed fails closed: a
 * robots.txt we cannot understand is treated as a disallow, not as silence"* —
 * is untouched and is right. What moved is what we may **say** about the
 * refusal, and the reader that needed it already existed and was live:
 * `BylineCheck::$ownRobotsRefused` decides whether an owner is shown a sentence
 * naming a file **on their own server** and told to have somebody edit it.
 */
enum RobotsVerdict: string
{
    /**
     * Their rules were read and permit this path — including the case where
     * there is no `robots.txt` at all, which is what a 404 means.
     */
    case Allowed = 'allowed';

    /**
     * Their rules were read and refuse this path. **This one is theirs**, and it
     * is the only verdict from which anything may tell an owner that their own
     * website is turning us away.
     */
    case Disallowed = 'disallowed';

    /**
     * Nothing was established. The request threw, the origin answered something
     * other than a readable body, or the file was too large to parse.
     *
     * ⛔ **NOT A RULE, AND THEREFORE NOT A THING THAT WAITING CANNOT FIX.**
     * {@see FetchOutcome::Refused}'s docblock says a refusal *"never cools down —
     * waiting changes nothing about a rule"*, which is true of every other
     * refusal the gateway makes and was false of this one for as long as the two
     * were the same value: a five-second outage at somebody's host is fixed by
     * exactly one thing, which is waiting.
     */
    case Unavailable = 'unavailable';

    /**
     * Whether the gateway may open a socket to the URL this was asked about.
     *
     * ⚠️ **ASKED RATHER THAN REMEMBERED**, and deliberately not spelled
     * `$verdict === RobotsVerdict::Allowed` at the call site: the failure mode of
     * getting it wrong is that we fetch a page a webmaster told us not to, which
     * is the one promise `40` §6.2 makes non-negotiable.
     */
    public function permitsFetching(): bool
    {
        return $this === self::Allowed;
    }
}
