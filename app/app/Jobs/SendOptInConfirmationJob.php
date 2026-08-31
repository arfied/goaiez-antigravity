<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AutopilotActionType;
use App\Enums\SendOutcomeStatus;
use App\Enums\SendRefusalReason;
use App\Exceptions\TextNotDeliverable;
use App\Models\Customer;
use App\Models\Location;
use App\Services\Messaging\OptInConfirmations;
use App\Services\Messaging\Outbound\SendOutcome;

/**
 * Confirm one customer's opt-in, off the request.
 *
 * ⚠️ **QUEUED, AND ON A PUBLIC REQUEST PATH THAT IS NOT A PREFERENCE** (3272).
 * `SendReviewInviteJob`'s reasoning applies with one clause more force: this
 * crosses a vendor boundary from `/f/{slug}`, which is a member of the public
 * waiting on a thank-you page. A carrier timeout — ten seconds by default, and
 * held *inside* `PlatformMessageSender`'s transaction — would otherwise be ten
 * seconds of a spinner and then a 500 on a form whose feedback was already
 * saved. **And it must not be synchronous for a second reason**: the send opens
 * a database transaction across that round trip, and doing it inside the
 * submission's own transaction would nest a vendor call inside the write that
 * created the consent record.
 *
 * ⚠️ **`AutopilotJob` RATHER THAN A PLAIN JOB**, on `SendReviewInviteJob`'s own
 * line between the two: this is a tenant's automation acting on their behalf, so
 * it wants the tenant established, the run row, the idempotency claim, the kill
 * switches and the activity feed. `DeliverPlatformMail` is the other kind — an
 * account holder, no tenant, no toggle.
 *
 * ⚠️ **THE TENANT PAUSE AND THE SUSPENSION SKIP THIS, AND THAT IS COHERENT
 * RATHER THAN A GAP** (3273). A paused or suspended tenant sends nothing at all,
 * so the sequence the confirmation exists to prevent — messages arriving before
 * anything explained who we are — cannot occur while they are stopped. The
 * confirmation and everything it precedes are held by the same gate, which is
 * the same argument that makes the credit debit affordable (3274).
 *
 * ## Two idempotency layers, and they are not redundant
 *
 * ⚠️ **THE RUN CLAIM ANSWERS "HAS THIS JOB RUN" AND THE `SendKey` ANSWERS "HAS
 * THIS MESSAGE GONE".** They are needed separately because they fail
 * differently: the run claim is released when an attempt did not earn it (see
 * `claimIsSpent()` below), so a retried job genuinely re-decides — and it is
 * then the send key, held by a unique index on `outreach_messages`, that stops a
 * retry after a *successful* send from texting somebody twice. Either alone
 * leaves a real hole; `SendReviewInviteJob` has only the first and says so.
 *
 * NO SEPARATE `handoff()` WORK, and the reason is not "it does not need one".
 * `CLAUDE.md` requires both paths in the same ticket precisely so nobody
 * retrofits them. Here they are genuinely identical: nothing in this job touches
 * the Google Business Profile API, so there is no reduced-capability version to
 * describe.
 */
final class SendOptInConfirmationJob extends AutopilotJob
{
    public function __construct(
        int $businessId,
        ?int $locationId,
        public readonly int $customerId,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return 'messaging.optin_confirmation';
    }

    /**
     * One confirmation per customer, ever.
     *
     * ⚠️ **PER CUSTOMER RATHER THAN PER SUBMISSION**, which is the whole point.
     * `FeedbackSubmission` records consent again on a resubmission — deliberately,
     * because a fresh tick is a fresh grant — and dispatches again with it. The
     * unique index on `(business_id, automation_key, idempotency_key)` is what
     * turns the second dispatch into a no-op rather than a second text.
     */
    protected function idempotencyKey(): string
    {
        return 'optin-confirmation:'.$this->customerId;
    }

    /**
     * ⚠️ **FALSE UNTIL THE SENDER HAS ANSWERED**, on `SendReviewInviteJob`'s
     * finding: without this, a failed run row keeps the unique key and all three
     * retries collide with the first attempt, so a queue blip loses the
     * confirmation permanently while the run rows read `failed` three times —
     * which looks exactly like a job that tried.
     *
     * ⚠️ **AND RELEASING IS SAFE ONLY BECAUSE OF WHERE THE ROW IS WRITTEN.**
     * `PlatformMessageSender` writes `outreach_messages`, debits the credit and
     * calls the carrier inside one transaction, so a throw rolls all three back
     * — a retry finds a free send key and genuinely re-decides. A committed row
     * before the send would make this have to become "did the row land".
     *
     * ⚠️ **SET PAST THE SENDER CALL ONLY.** Every earlier `return null` is an
     * ordinary outcome — no customer, no location, the switch off, the daily
     * ceiling, a permit refused — that cost nothing, so leaving the claim
     * unearned lets a later dispatch re-decide it for free.
     *
     * ⛔ **AND A REFUSAL *FROM* THE SENDER IS ALSO AN ORDINARY OUTCOME, WHICH
     * THIS GOT WRONG UNTIL 3277.** See `confirm()`: the halt, a pause, the
     * complaint trip and an empty balance are **states that clear**, not
     * verdicts, and burning the claim on one of them loses the confirmation
     * permanently.
     *
     * ⛔ **AND A TRANSPORT FAILURE NOW ANSWERS THIS RATHER THAN FALLING THROUGH
     * TO "RELEASE" — 7067.** A thrown {@see TextNotDeliverable} used to skip
     * every assignment below, leaving the flag `false`, so the claim went back
     * and the queue tried again. **That is right for a request that provably
     * never left this machine and it is a second text to a member of the public
     * for one that may already be with the carrier** — and until 2026-08-21 the
     * transport could not tell those apart, so this job could not either. The
     * argument this docblock already makes establishes that the retry is
     * *unblocked*; it was read for months as establishing that the retry is
     * *safe*. Those are different claims and only the first was ever true.
     *
     * ⚠️ **THE COST IS A LOST MESSAGE, AND IT IS THE SIDE THIS CODEBASE HAS
     * ALREADY CHOSEN IN WRITING.** A blip that timed out before the carrier saw
     * anything is indistinguishable from one that timed out after, so both stop
     * rather than retry: nothing is sent, nothing is charged, and
     * `automation_runs` carries the reason. `AutopilotJob::claimIsSpent()`
     * settled the trade — *"losing a send is recoverable; sending twice is
     * not"* — and this restores the base class's own ruling everywhere except
     * where a retry is provably safe.
     */
    private bool $claimSpent = false;

    protected function claimIsSpent(): bool
    {
        return $this->claimSpent;
    }

    /**
     * Whether this attempt actually put a confirmation on somebody's handset.
     *
     * ⚠️ **NARROWER THAN {@see self::$claimSpent}, AND THE GAP IS THE WHOLE
     * REASON IT IS A SECOND FLAG** — {@see SendReviewInviteJob::$invited}'s
     * shape, for its reason. The claim is earned by anything that means a
     * message exists on the wire, **including a `Duplicate`**, which is an
     * earlier attempt's send and not this one's. This flag is the feed's
     * question: did *this* run tell a person anything?
     */
    private bool $confirmed = false;

    /**
     * ⛔ **THIS JOB DECLARED NOTHING AND SO FILED `AutomationCompleted` ON
     * EVERY ARM — 7222 ON THE CONSENT PATH** (7322). *"Finished a piece of work
     * for you"* went to the owner's feed when the customer had been deleted
     * between the submission and this job, when the location had gone, and on
     * **every** `Refused` outcome — which {@see self::$claimSpent} enumerates as
     * the global halt, a tenant pause, 2102's complaint trip and an exhausted
     * balance. ⛔ **The last of those is the normal state and this file already
     * said so**: 3102 records that nothing in `app/` funds a credit balance, so
     * the common history for a tenant reading their feed was a run of completed
     * work in which no confirmation was ever sent.
     *
     * ⚠️ **THE SENTENCE THAT SURVIVES IS STILL THE CATCH-ALL, AND THAT IS A
     * VOCABULARY GAP RATHER THAN A CHOICE** (7333). There is no
     * `AutopilotActionType` case for *"confirmed a customer's text sign-up"*,
     * and `AutopilotActionType.php` belongs to another lane in this wave. What
     * is fixed here is the falsehood — a row now means a message went — and
     * what is left is a true row that says little.
     */
    protected function activityAction(): ?AutopilotActionType
    {
        return $this->confirmed ? AutopilotActionType::AutomationCompleted : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function execute(): array
    {
        return $this->confirm();
    }

    /**
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        return $this->confirm();
    }

    /**
     * ⛔ **IT NEVER RETURNS NULL NOW, AND THAT IS THE POINT RATHER THAN A
     * TIDY-UP** (10180). `AutopilotJob::handle()` writes whatever this returns
     * straight into `automation_runs.output` and closes the row `Succeeded`, so
     * a `null` here is a run that finished with **no account of itself at all** —
     * indistinguishable, for ever, from one that has not been written yet.
     *
     * ⚠️ **TWO ARMS OF THIS ARE STILL BLIND, AND THEY ARE NAMED RATHER THAN
     * GLOSSED.** `OptInConfirmations::send()` still returns a bare `null` for
     * two of its own gates — `sms.optin_confirmation_enabled` being off, and
     * 3278's daily containment ceiling — so on those the row reads
     * `{confirmed: false, refusal: null}` and cannot say which. Closing that
     * means the service answering with a `SendOutcome::refused()` rather than a
     * null, which needs a {@see SendRefusalReason} case for a
     * containment ceiling and is therefore a conversation about that enum, not
     * an edit here. ⚠️ **A null `refusal` beside `confirmed: false` therefore
     * means *the sender declined before it produced an outcome*** — the same
     * reading `SendMissedCallTextBackJob` gives a `Duplicate`.
     *
     * ⛔ **THE THIRD ARM IS NO LONGER BLIND — 10240, PHASE 4.**
     * `OptInConfirmations::send()` used to ask `ConsentService::permit()`,
     * throwing away the one gate on this path with a genuinely typed reason —
     * the platform-wide `opt_outs` register, the litigator list, the
     * reassigned-numbers check, an unloaded scrubbing register — into the same
     * anonymous `null` as the two gates above it. It now asks `decide()` and
     * answers the bare {@see SendRefusalReason} it computed, on
     * `MissedCallTextBack::textBack()`'s own `SendOutcome|SendRefusalReason`
     * shape. This method's own claim-spend rule is unchanged in effect: a bare
     * reason is treated exactly as the old bare `null` was — the claim is not
     * spent, because a compliance refusal answered before a `SendKey` could
     * even be minted is a state with nothing on the wire and nothing debited,
     * the same category `PlatformMessageSender`'s own refusals already sit in.
     *
     * @return array<string, mixed>
     */
    private function confirm(): array
    {
        $location = $this->location();
        $customer = Customer::query()->find($this->customerId);

        // ⚠️ Ordinary outcomes rather than torn state. A contact deleted between
        // the submission and this job running is the realistic case, and
        // `Customer::query()` is tenant-scoped, so a customer id belonging to
        // somebody else resolves to null here rather than being messaged.
        if (! $location instanceof Location || ! $customer instanceof Customer) {
            return ['confirmed' => false, 'skipped' => 'contact_missing'];
        }

        try {
            $outcome = app(OptInConfirmations::class)->send($customer, $location->businessName());
        } catch (TextNotDeliverable $e) {
            // ⛔ **A TRANSPORT FAILURE IS NEITHER A VERDICT NOR A STATE, AND IT
            // IS THE ONE CASE THE ARGUMENT ABOVE DOES NOT COVER** (7067). 3277
            // split refusals from sends because a refusal is a *state that
            // clears*; this is a third thing — an outcome nobody knows. If the
            // confirmation may already be on a handset, a retry sends the
            // customer a second one, and a second "you are subscribed" text is
            // the message this path exists to get exactly right.
            $this->claimSpent = $e->mayHaveReachedCarrier;

            throw $e;
        }

        // ⛔ **ONLY THE TWO OUTCOMES THAT MEAN A MESSAGE EXISTS ON THE WIRE
        // SPEND THE CLAIM** (3277). This line read `$outcome !== null` and the
        // docblock argued that a refusal *"got its answer, and re-running would
        // only ask the same question again"*. **That is true of a verdict and
        // false of a state, and three of the four refusals are states**:
        // `PlatformMessageSender` returns a non-null `SendOutcome` for the
        // global halt, a tenant pause, 2102's complaint trip, an exhausted
        // balance, and a driver that could not carry it. Every one of those
        // spent the claim, so `releaseUnearnedClaim()` never ran and
        // `optin-confirmation:{customerId}` stood for ever.
        //
        // ⛔ **THE WINDOW IS NOT HYPOTHETICAL AND IT IS THE COMMON CASE.** 3102
        // records that nothing in `app/` funds a credit balance, so "no credit"
        // is the *normal* state — and every customer who consented while the
        // platform was halted or the balance was empty was marked confirmed and
        // never confirmed. Their first ever text would then be the review invite
        // with a link in it: precisely the sequence 3260 says the filing
        // declares does not happen.
        //
        // ⚠️ **A DUPLICATE SPENDS IT AND A REFUSAL DOES NOT.** `Duplicate` means
        // the send key was already claimed, so a message for this occasion
        // exists and re-running could only produce a second; a refusal means no
        // row, no debit and nothing on the wire, so a later dispatch must be
        // free to re-decide once the condition clears. This also restores
        // 3274's affordability argument, which only ever held *while* the
        // refusal lasted.
        //
        // ⛔ **A BARE `SendRefusalReason` IS THE SAME CATEGORY AS A `SendOutcome`
        // REFUSAL, NEVER THE `instanceof SendOutcome` ARM — 10240, PHASE 4.**
        // It answers before a `SendKey` could even be minted, so there is
        // nothing on the wire and nothing debited either way; it is treated
        // exactly as the old bare `null` from the same gate was, which is what
        // keeps this line's *effect* unchanged while its *typing* stopped
        // losing the reason.
        $this->claimSpent = $outcome instanceof SendOutcome
            && $outcome->status !== SendOutcomeStatus::Refused;

        // ⚠️ **A SECOND, NARROWER QUESTION** (7322). See
        // {@see self::activityAction()}: `wasSent()` is the only answer that
        // means a person was told something on this run.
        $this->confirmed = $outcome instanceof SendOutcome && $outcome->wasSent();

        // ⚠️ **WHETHER, NEVER TO WHOM** — decision 627's rule. `automation_runs`
        // is read by staff and by the Ops console, and a number here would put a
        // customer's mobile in a table nobody thinks of as holding personal data.
        //
        // ⛔ **AND WHY NOT, WHICH THIS LINE DISCARDED UNTIL 10180.** It read
        // `return ['confirmed' => $this->confirmed];`, so the global halt, a
        // tenant pause, 2102's complaint trip, an exhausted balance and a
        // driver that could not carry it — five materially different causes,
        // each with a different remedy and a different person to tell —
        // collapsed into one `false`. {@see SendOutcome}'s constructor
        // *throws* on a refusal that carries no reason (*"a dead end for
        // whoever has to explain it"*), and this method threw the reason away
        // one line later, into the only durable record the event has.
        // `SendMissedCallTextBackJob` has carried `refusal` correctly since it
        // shipped; this is the same key with the same contract.
        return [
            'confirmed' => $this->confirmed,
            'refusal' => $this->refusalOf($outcome),
        ];
    }

    /**
     * The one string worth keeping from either shape `send()` can answer.
     *
     * ⚠️ **`SendMissedCallTextBackJob::refusalOf()`'s SAME SPLIT, WIDENED TO
     * ADMIT `null`** (10240, phase 4) — that method's subject is never null,
     * because `MissedCallTextBack::textBack()` cannot yet answer one; this
     * one still can, from the two gates named in {@see self::confirm()}'s own
     * docblock.
     */
    private function refusalOf(SendOutcome|SendRefusalReason|null $outcome): ?string
    {
        if ($outcome instanceof SendRefusalReason) {
            return $outcome->value;
        }

        // A `Duplicate` is neither a send nor a refusal, and the run row says
        // so by carrying `confirmed: false` with no reason — the reason it did
        // not send is that it already had, which the first run's own row
        // records. A bare `null` reaches here too, from the two gates that are
        // still blind, and answers the same way.
        return $outcome?->reason?->value;
    }
}
