<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\InviteAttemptStatus;
use App\Enums\SendOutcomeStatus;
use App\Enums\SendRefusalReason;
use App\Models\OutreachMessage;
use App\Services\Messaging\Outbound\SendOutcome;
use LogicException;

/**
 * What happened when this application tried to ask one customer for a review.
 *
 * ⛔ **THIS EXISTS BECAUSE A REFUSAL DID NOT SURVIVE ITS OWN ROLLBACK.**
 * {@see ReviewInviteSender::sendText()} writes a short link, an
 * `outreach_messages` row and a credit debit inside one transaction, and then
 * — on four separate conditions — rolls all three back and returns `null`.
 * **The rollback is correct and is untouched**: an unsent invite must not leave
 * a spent credit, a live tracked link and a row claiming a send, and a
 * committed row would make gate 3 refuse that review an invite for ever. What
 * was wrong is that *nothing at all* came back out. Four rules, one `null`,
 * and no record anywhere that an attempt had been made.
 *
 * ⚠️ **A VALUE IS THE ONLY THING THAT CAN SURVIVE A ROLLBACK, WHICH IS WHY
 * THIS IS A TYPE AND NOT A ROW.** A row written to explain the refusal is
 * inside the transaction that is being thrown away; a row written outside it is
 * the store of refused attempts **decision 10044 refuses in writing** — a
 * tenant-owned table with RLS, a policy, a retention period and a new row per
 * member of the public who left feedback, against every one of `CLAUDE.md`'s
 * tiebreakers. *"The history is the owner's to ask for."* A returned value
 * costs nothing, stores nothing and is computed before `rollBack()` is called.
 *
 * ⛔ **IT IS NOT {@see SendOutcome} AND COULD
 * NOT HAVE BEEN.** That type is the working half of this pair and is
 * deliberately unchanged — `CLAUDE.md` §Engineering's rule about levelling an
 * honest sibling down to match a broken one. Three things stop it serving here,
 * and the first two are structural rather than aesthetic:
 *
 *   - **Two of this path's refusals happen before a `SendKey` exists.** The
 *     containment and the missing permit are decided before
 *     `inviteSendKey()` is derived, and every `SendOutcome` factory requires a
 *     key. There is nothing to construct one from.
 *   - **An accepted review invite has no provider message id on the email
 *     channel.** The mail leaves through a queued `DeliverPlatformMail`, so
 *     `SendOutcome::accepted()` — which throws without one — cannot be built
 *     for the very case it is meant to describe.
 *   - **The caller needs the `OutreachMessage`,** not a provider id: it is what
 *     `SendReviewInviteJob` reports on and what `recordCost()` keys against.
 *
 * ⚠️ **WHAT IS BORROWED IS THE SHAPE, AND ON PURPOSE.** The three-state
 * vocabulary, the invariants enforced in the constructor rather than
 * documented, and `wasSent()` asserting rather than each caller re-checking are
 * all `SendOutcome`'s, so a reader who knows one knows this one.
 *
 * ⚠️ **THE REASON IS SAFE TO CARRY AND IS NOT YET SAFE TO RENDER ON THE EMAIL
 * CHANNEL.** {@see SendRefusalReason::ownerSentence()} answers *"we have no
 * mobile number for them"*, *"their mobile number could not be read"* and
 * *"text messaging was unavailable"* — three sentences written when the only
 * renderers were an SMS campaign and an SMS inbox. **A screen that prints this
 * value for a refused email invite would tell a tenant about a mobile number
 * nobody was ever going to use.** That is a finding of this slice and it is
 * owed before an owner-facing surface reads a reason from here.
 */
final readonly class InviteAttempt
{
    private function __construct(
        public InviteAttemptStatus $status,
        public ?OutreachMessage $message,
        public ?SendRefusalReason $reason,
    ) {
        if ($status === InviteAttemptStatus::Refused && $reason === null) {
            throw new LogicException(
                'A refusal without its reason is the defect this type exists to close. '
                .'ConsentService has answered with a reason since 391; this must not lose it again.'
            );
        }

        if ($status !== InviteAttemptStatus::Refused && $reason !== null) {
            throw new LogicException(
                'An attempt that was not refused must not carry a refusal reason. An owner screen '
                .'would print the refusal beside the invite that went out.'
            );
        }

        if (($status === InviteAttemptStatus::Sent) !== ($message !== null)) {
            throw new LogicException(
                'A sent invite is the row it wrote, and nothing else is. A message on a refused '
                .'attempt is a row the rollback was supposed to have taken.'
            );
        }
    }

    public static function sent(OutreachMessage $message): self
    {
        return new self(InviteAttemptStatus::Sent, $message, null);
    }

    /**
     * A rule said no, and this is the rule.
     *
     * ⚠️ **EVERY CALLER PASSES A REASON IT WAS ALREADY HOLDING**, never one it
     * derived a second time: `SendingGuard::refusalFor()` and
     * `ConsentService::daytimeWindowRefusal()` both return one, and
     * `ConsentService::decide()` computes one in the same traversal that
     * decides the permit. **Re-deriving a reason beside a refusal is two
     * implementations of one rule**, which is `SendDecision`'s own argument for
     * why `permit()` unwraps `decide()` rather than asking again.
     */
    public static function refused(SendRefusalReason $reason): self
    {
        return new self(InviteAttemptStatus::Refused, null, $reason);
    }

    /**
     * This review's invite was already claimed. Nothing new happened.
     *
     * ⚠️ **NOT A REFUSAL**, on {@see SendOutcomeStatus::Duplicate}'s
     * argument: no rule refused this customer, an earlier attempt got there
     * first, and a caller that recorded it as a refusal would be filing a
     * compliance reason against a message that did go out.
     */
    public static function duplicate(): self
    {
        return new self(InviteAttemptStatus::Duplicate, null, null);
    }

    /**
     * Nothing about this person was ever asked. See the enum case for the three
     * states this deliberately fuses and why.
     */
    public static function notAttempted(): self
    {
        return new self(InviteAttemptStatus::NotAttempted, null, null);
    }

    /**
     * Whether a message actually went to a person.
     *
     * ⚠️ **THE ASSERTION RESTATES AN INVARIANT THE CONSTRUCTOR ALREADY REFUSES
     * TO BREAK**, rather than adding a second rule beside it — `SendOutcome`'s
     * own note about `wasSent()`. Past this method the message is a model, and
     * the analyser is told so here instead of every caller re-checking a case
     * that cannot occur.
     *
     * @phpstan-assert-if-true !null $this->message
     */
    public function wasSent(): bool
    {
        return $this->status === InviteAttemptStatus::Sent;
    }
}
