<?php

declare(strict_types=1);

namespace App\Enums;

use App\Jobs\PollSupportMailboxJob;
use App\Services\Support\AccountDirectory;
use App\Services\Support\FetchedSupportMail;

/**
 * What the support mailbox knows about one message's sender — 4610's owed
 * split, built at 4645.
 *
 * ⛔ **THIS IS A TALLY, NOT AN AUTHORISATION, AND THE DISTINCTION IS THE WHOLE
 * REASON IT CAN EXIST AT ALL.** 4606 was a security defect in which a
 * sender-written `From` resolved a tenant *and* an author, and 4607's fix was to
 * make the routing call take a {@see FetchedSupportMail} so no call shape could
 * skip the provider's verdict. Nothing here weakens that: **no case of this enum
 * carries a `business_id`, a `user_id` or an address**, and
 * {@see AccountDirectory::accountOfSender()} is untouched and is still the only
 * thing that returns one. A message whose standing is {@see self::Spoofed} is
 * routed exactly as far as a stranger's — which is to say nowhere.
 *
 * ## Why the split is worth making
 *
 * ⚠️ **BEFORE THIS, A SUSTAINED SPOOFING ATTEMPT AND A QUIET WEEK PRODUCED THE
 * SAME LOG LINE** (4610). `PollSupportMailboxJob` counted an unauthenticated
 * sender in the same `unrecognised` tally as a stranger, which was the right
 * call about *cost* — the mail waits in the mailbox for a person either way —
 * and the wrong one about *attention*. An `Authentication-Results` failure is a
 * different fact from an address nobody recognises, and only one of them is
 * somebody trying something.
 *
 * ## The two axes, and why the enum is total rather than ordered
 *
 * Two booleans: did the mailbox provider authenticate the `From` domain, and
 * does that address name an account owner we hold? All four combinations have a
 * case, so this is a **total function of the pair** and never depends on having
 * been asked in a particular order. That matters more than it looks:
 * {@see AccountDirectory::senderStanding()} is called *after*
 * `accountOfSender()` has already returned null, and a case that caller can
 * never see is honest where a three-case enum would have had to document
 * *"only meaningful if you already asked something else"* — a method whose
 * correctness depends on its caller's ordering is 398's shape pointed at a
 * tally.
 *
 * ⛔ **`Spoofed` IS THE WEAKEST CLAIM THIS CODEBASE CAN MAKE AND ITS NAME IS
 * DELIBERATELY NOT PROOF.** It means *the provider did not authenticate this
 * message and its `From` names an owner we hold* — which is what an
 * impersonation attempt looks like, and also what a **misconfigured
 * `authserv-id` looks like on entirely genuine mail** (4608). Nothing here has
 * ever seen a real `Authentication-Results` header, so on the first day of a
 * live support mailbox the likelier reading of a non-zero count is our own
 * configuration. The log line says both; see {@see PollSupportMailboxJob} for
 * why this is a `warning` and deliberately not an operator alert.
 */
enum SupportSenderStanding: string
{
    /**
     * Authenticated, and the address names an account owner.
     *
     * ⚠️ **NEVER SEEN IN THE TALLY, BY CONSTRUCTION**, because the poller only
     * asks about a message `accountOfSender()` has already refused to route.
     * It exists so the enum is total, and a test pins the agreement between the
     * two methods rather than leaving it to the two docblocks.
     */
    case Routed = 'routed';

    /**
     * Not authenticated, and the address names an account owner.
     *
     * The one an operator wants to see. See the class docblock for what it
     * cannot prove.
     */
    case Spoofed = 'spoofed';

    /**
     * Not authenticated, and the address names nobody we hold.
     *
     * ⚠️ **ORDINARY SPAM IS THIS, AND KEEPING IT OFF `Spoofed` IS THE POINT.**
     * Counting every unauthenticated message together would make a spam wave
     * and an impersonation attempt the same number, which is 4610's complaint
     * one level in rather than answered.
     */
    case Unauthenticated = 'unauthenticated';

    /**
     * Authenticated, and the address names nobody we hold — a stranger.
     *
     * The overwhelmingly ordinary case: somebody emailed support who is not a
     * customer, and their mail waits in the mailbox for a person.
     */
    case Unrecognised = 'unrecognised';

    /**
     * Whether the mailbox provider authenticated the `From` domain.
     */
    public function senderWasAuthenticated(): bool
    {
        return match ($this) {
            self::Routed, self::Unrecognised => true,
            self::Spoofed, self::Unauthenticated => false,
        };
    }

    /**
     * Whether a run that produced any of these is worth an operator's attention.
     *
     * ⚠️ **ONE PLACE RATHER THAN A CONDITION IN THE JOB** (4485's rule at enum
     * scope): the log line and any later reader agree about which standing
     * raises a warning by construction, rather than by two callers remembering
     * the same case name.
     */
    public function wantsAnOperator(): bool
    {
        return $this === self::Spoofed;
    }
}
