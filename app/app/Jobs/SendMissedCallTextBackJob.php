<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AutopilotActionType;
use App\Enums\SendOutcomeStatus;
use App\Enums\SendRefusalReason;
use App\Exceptions\TextNotDeliverable;
use App\Services\Messaging\Outbound\SendOutcome;
use App\Services\Voice\InboundCall;
use App\Services\Voice\MissedCallTextBack;
use App\Services\Voice\VoiceCalls;

/**
 * SM-001, off the webhook — one text-back for one missed call.
 *
 * ⚠️ **QUEUED, AND NOT BECAUSE THE WORK IS SLOW.** It crosses a vendor boundary
 * on a path a carrier is waiting on: a voice webhook that blocked on Infobip's
 * SMS endpoint would time out and be **redelivered**, which on this feature
 * means a second apology to a member of the public. The queue is what puts the
 * retry ladder under our control rather than the carrier's.
 *
 * ⚠️ **`AutopilotJob` RATHER THAN A PLAIN JOB**, for `SendReviewInviteJob`'s
 * reason exactly: this is a tenant's automation acting on their behalf, so it
 * wants the tenant established, the run row, the idempotency claim, the kill
 * switches and the activity feed. `29` §2 rule 40 in one base class.
 *
 * ## Two idempotency layers, and they guard different things
 *
 *   1. **This job's `idempotencyKey()`** stops a redelivered webhook from
 *      reaching the sender at all — a `unique` index on `automation_runs`,
 *      arbitrated by the database rather than by a preceding SELECT.
 *   2. **The `SendKey`** inside {@see MissedCallTextBack} stops a message going
 *      out twice even if layer 1 is somehow bypassed — a different dispatch
 *      path, a released claim, two workers. It is the layer that holds the
 *      *debit* as well, because `PlatformMessageSender` claims the key and
 *      debits the credit in one transaction.
 *
 * Both derive from `CallMissed::occasion()`, which is where the vendor's call id
 * enters this feature and the only place it is turned into a string.
 *
 * ## NO SEPARATE `handoff()` WORK, AND THE REASON IS NOT "IT DOES NOT NEED ONE"
 *
 * `CLAUDE.md` requires both paths in the same ticket precisely so nobody
 * retrofits them. Here they are genuinely identical: nothing in this job touches
 * the Google Business Profile API — the trigger is a carrier voice event and the
 * send is our own SMS path — so there is no reduced-capability version to
 * describe. The row gate is *"the whole review engine runs with zero GBP API
 * access"*, and this is a demonstration of it rather than an exception to it.
 *
 * ✅ **THE TRIGGER EXISTS SINCE T176 P2 — THIS PARAGRAPH SAID THE OPPOSITE AND
 * IS CORRECTED RATHER THAN DELETED.** It read *"nothing in `app/` dispatches
 * `CallMissed` yet, so this job has no production trigger"*, which was true when
 * written and would have gone stale silently — 2505's shape, in the docblock a
 * reader of this job opens first. {@see VoiceCalls} now
 * dispatches the event, fed by `POST /webhooks/infobip/voice`.
 *
 * ⛔ **AND IT IS STILL INERT, WHICH IS A DIFFERENT THING FROM ABSENT.** 2109's
 * external ask stands — T176 §7 item 3, *"activate Voice/Calls API (gates P2)"*
 * — so `VOICE_DRIVER` seeds `null` and `voice.enabled` seeds false. What changed
 * is that the path from a carrier webhook to this job is written, wired and
 * exercised end to end by the suite against a fake provider, so activation is a
 * credential change rather than a first build. **The payload shape was not
 * invented**: Infobip's Calls event webhook publishes no per-event body at all,
 * so the call is read back from the documented `GET /calls/1/calls/{callId}`.
 */
final class SendMissedCallTextBackJob extends AutopilotJob
{
    public function __construct(
        int $businessId,
        ?int $locationId,
        public readonly InboundCall $call,
        /**
         * `CallMissed::occasion()`, carried rather than recomputed.
         *
         * ⚠️ **THE EVENT OWNS THIS STRING AND THIS JOB MUST NOT DERIVE ITS
         * OWN.** Two derivations of "the occasion of this missed call" will
         * eventually disagree, and the day they do a redelivered webhook sends
         * the caller two apologies — the event's own docblock says so, which is
         * why it is a parameter.
         */
        public readonly string $occasion,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return \App\Enums\AutopilotActionType::CallMissed->value;
    }

    /**
     * One text-back per missed call, ever.
     *
     * The occasion already carries the vendor's call id and the tenant is
     * implicit in the `automation_runs` row's own scoping, so this is unique to
     * exactly one call.
     */
    protected function idempotencyKey(): string
    {
        return 'missed-call-text-back:'.$this->occasion;
    }

    /**
     * ⚠️ FALSE UNTIL THE SENDER HAS ANSWERED, WHICH IS WHAT MAKES THE RETRY
     * LADDER REAL. `AutopilotJob::claimIsSpent()` defaults to `true` on purpose
     * — losing a send is recoverable and sending twice is not — but a keyed job
     * that never releases collides with its own failed attempt on every retry
     * and burns the ladder without running any of it.
     *
     * ⚠️ **RELEASING IS SAFE HERE ONLY BECAUSE OF WHERE THE SEND ROW IS
     * WRITTEN.** `PlatformMessageSender` writes the `outreach_messages` row, the
     * credit debit and the carrier call inside one transaction and rolls all
     * three back on a throw — so a retry finds no claimed `send_key`, and the
     * decision is genuinely re-made rather than blocked by its own abandoned
     * evidence. If that row were ever committed before the wire call, this would
     * have to become "did the row land" instead.
     *
     * ⛔ **THIS DOCBLOCK CONTRADICTED THE CODE FOR ONE COMMIT, AND THE CODE IS
     * WHAT CHANGED** (3185). It said a refusal left the claim unearned so a
     * later dispatch could re-decide for free; `textBack()` set the flag on
     * **every** non-throwing return, refusals included. The consequence is
     * concrete: a tenant at zero credits refuses, the refusal spends the
     * idempotency key, the owner tops up, the carrier redelivers — and the job
     * no-ops against its own refused attempt while the run row reads *succeeded,
     * `sent: false`*. ⚠️ **This is 314–316's shape inside the slice that quotes
     * 314–316**, which is exactly where the recurring failures in this codebase
     * keep landing.
     *
     * ⚠️ **THE CLAIM IS SPENT WHEN THE SENDER *WROTE* SOMETHING, NOT WHEN IT
     * ANSWERED.** An accepted send and a `Duplicate` both mean a message exists
     * for this occasion and nothing more must happen; a bare
     * {@see SendRefusalReason}, or a `SendOutcome` carrying one, means nothing
     * was written, nothing was debited and the whole decision is free to be
     * re-made.
     *
     * ⚠️ **AND RELEASING IS SAFE ONLY BECAUSE THE `SendKey` LAYER EXISTS.** If
     * releasing the run claim were the only thing standing between a redelivery
     * and a second apology this would be reckless — but 3172's second layer is
     * the `outreach_messages` unique index, arbitrated by the database, and it
     * holds whatever this method answers. **Do not release here without that
     * index.**
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
     * Whether this attempt actually put a message on the wire.
     *
     * Read by {@see self::activityAction()}, which must stay silent otherwise:
     * `AutopilotActionType::CallMissed`'s sentence is *"Texted back a missed
     * call"*, and writing it after a refusal would tell an owner a stranger was
     * answered when nobody was.
     */
    private bool $sent = false;

    /**
     * ⚠️ **NARROWED TO A NON-NULLABLE `array`, WHERE THE BASE CLASS ALLOWS
     * NULL.** `AutopilotJob::execute()` permits null for an automation with
     * nothing to say; this one always has something — whether a text went, and
     * if not, which gate refused — and a run row with no output would lose the
     * only record of a refusal on the path that most needs one.
     *
     * @return array<string, mixed>
     */
    protected function execute(): array
    {
        $res = $this->textBack();
        \Illuminate\Support\Facades\Log::info("TextBack job outcome: " . json_encode($res));
        return $res;
    }

    /**
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        return $this->textBack();
    }

    /**
     * @return array<string, mixed>
     */
    private function textBack(): array
    {
        try {
            $result = app(MissedCallTextBack::class)->textBack($this->call, $this->occasion);
        } catch (TextNotDeliverable $e) {
            // ⛔ **THE `SendKey` LAYER THIS DOCBLOCK LEANS ON DOES NOT HOLD
            // HERE, WHICH IS WHY THE FLAG IS READ** (7067). `$claimSpent`'s own
            // note says releasing is safe *"only because 3172's second layer is
            // the `outreach_messages` unique index, arbitrated by the
            // database"* — but on a thrown transport failure that index is
            // rolled back with everything else, so on this one path the second
            // layer is not there. A retry after an unknown outcome is a second
            // apology to somebody who rang once.
            $this->claimSpent = $e->mayHaveReachedCarrier;

            throw $e;
        }

        $this->sent = $result instanceof SendOutcome && $result->wasSent();

        // ⚠️ **SPENT WHEN THE SENDER WROTE A ROW, NOT MERELY WHEN IT ANSWERED**
        // — see `$claimSpent`'s own docblock for the defect this replaced. A
        // `Duplicate` counts because the message already exists for this
        // occasion; a refusal does not, because nothing was written and nothing
        // was debited. A throw skips this line entirely and the base class
        // releases the claim.
        $this->claimSpent = $result instanceof SendOutcome
            && ($result->wasSent() || $result->status === SendOutcomeStatus::Duplicate);

        // ⚠️ **THE OUTPUT RECORDS WHETHER A TEXT WENT AND WHY NOT — NEVER TO
        // WHOM.** `AutopilotJob` writes this onto the run row, which staff and
        // the Ops console read; a number here would put a member of the public's
        // mobile in a table nobody thinks of as holding personal data. Decision
        // 627's warning about `metadata`, applied before it becomes a finding.
        // Every `SendRefusalReason` is safe to show an operator by that enum's
        // own contract: each case names a rule, never a contact detail.
        return [
            'sent' => $this->sent,
            'refusal' => $this->refusalOf($result),
        ];
    }

    private function refusalOf(SendOutcome|SendRefusalReason $result): ?string
    {
        if ($result instanceof SendRefusalReason) {
            return $result->value;
        }

        // A `Duplicate` is neither a send nor a refusal, and the run row says so
        // by carrying `sent: false` with no reason — the reason it did not send
        // is that it already had, which the first run's own row records.
        return $result->reason?->value;
    }

    /**
     * ⛔ **SILENT UNLESS A TEXT ACTUALLY WENT.** `29` §2 rule 42 wants every
     * automated action in the feed, and `AutopilotJob`'s own docblock carries
     * the counterweight: *"an automation whose only honest title is 'checked
     * something and found nothing' makes the feed worse."* A refused text-back
     * is exhaustively recorded in `automation_runs` with its reason; putting
     * "Texted back a missed call" in the owner's feed for one would be a false
     * statement about a stranger's experience of their business.
     */
    protected function activityAction(): ?AutopilotActionType
    {
        return $this->sent ? AutopilotActionType::CallMissed : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        // ⚠️ **THE CALL'S PROVIDER ID AND NOTHING ELSE ABOUT THE CALLER.** The
        // base class's `input()` is the dispatch record a sweeper counts
        // attempts from, so it needs to name the thing acted on; `InboundCall`
        // also carries the caller's mobile number, and that must not travel into
        // a run row. The occasion is already the id in string form.
        return array_merge(parent::input(), ['occasion' => $this->occasion]);
    }
}
