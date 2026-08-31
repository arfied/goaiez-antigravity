<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AgentThreadStatus;
use App\Enums\AutopilotActionType;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\AssistantThreadClosed;
use App\Services\Agent\AgentThreadStates;
use App\Services\Agent\ThreadCloseSummaries;
use App\Services\Agent\ThreadCloseSummary;
use App\Services\AuditService;
use App\Services\Mail\PlatformMailer;
use App\Support\Tenancy;

/**
 * Tell the owner how a conversation ended — T176 §2.3 rail 9, patch P13.
 *
 * *"Every thread close (resolved, escalated, or capped) → one-line outcome
 * summary in the owner notify."*
 *
 * ## ⛔ THE TWO BOOKS CARRY DELIBERATELY DIFFERENT THINGS
 *
 * `ActivityService` is what the owner reads and `AuditService` is what
 * compliance reads — `AgentThreadStates::record()`'s rule — and P13 splits them
 * one step further than that:
 *
 *  - **the feed and the notification carry the sentence**, because that is who
 *    it is for and both are correctable surfaces;
 *  - **the audit log carries the fact and never the sentence**: the thread, the
 *    status it closed in, and whether a model wrote anything. The append-only
 *    store is the one nobody can correct, and a fallible characterisation of a
 *    real conversation is the last thing that should live there. A reader of
 *    that log years later must not be able to mistake a model's reading for a
 *    record of what a customer said.
 *
 * ⛔ **AND NEITHER BOOK CARRIES A MESSAGE BODY.** The words live on the thread,
 * under the consent gate that admitted them, and copying them into two
 * append-only stores would put them where an erasure request cannot reach them
 * cleanly.
 *
 * ## ⚠️ IT IS NOT DISPATCHED ON EVERY SILENCE
 *
 * Rail 9 names three: resolved, escalated, capped. **A latched thread is not one
 * of them** — a person taking a conversation over has not finished it, they have
 * started answering it, and mailing them a summary of a conversation they are
 * mid-way through reading is noise. `AgentThreadStates` is what decides, and it
 * dispatches this from exactly the three places.
 *
 * ## The spend
 *
 * ⚠️ **`canExecute()` DOES NOT GATE ON THE AI BALANCE, AND THE ASYMMETRY WITH
 * `AnswerAgentTurnJob` IS DELIBERATE.** That job refuses when the balance is
 * gone because its whole purpose is a model call. This one has a real, useful
 * output with no model at all — the factual line — so refusing outright would
 * turn an exhausted balance into *"the owner stops being told their
 * conversations finished"*, which is a worse failure than the one it avoids.
 * `AiRouter` answers unusable, `ThreadCloseSummaries` degrades, the notify still
 * goes, **and nothing throws** (2904).
 */
final class SummariseClosedThreadJob extends AutopilotJob
{
    /**
     * ⛔ **THERE IS NO `$summary` PROPERTY, AND ITS REMOVAL IS PART OF 7460.**
     * The summary was held on the instance for one reason — `activityAction()`
     * and `activityMetadata()` read it after `execute()` returned. Neither does
     * now: the feed row for a close belongs to {@see AgentThreadStates}, and
     * the sentence goes to the owner by mail from inside the arm that produced
     * it. A property nothing reads is what Larastan would call dead and what a
     * later reader would call a hook.
     */
    private bool $notified = false;

    public function __construct(
        int $businessId,
        ?int $locationId,
        public readonly int $conversationId,
        public readonly AgentThreadStatus $status,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return 'agent.thread_close_summary';
    }

    /**
     * ⚠️ **KEYED ON THE THREAD AND THE STATUS IT CLOSED IN.** A thread closes
     * once, so the conversation alone would do — the status is carried because a
     * thread that escalated and was later closed by a person is two rail-9
     * events, and keying on the thread alone would silently drop the second.
     */
    protected function idempotencyKey(): string
    {
        return 'thread-close-summary:'.$this->conversationId.':'.$this->status->value;
    }

    /**
     * ⚠️ **ANSWERED EXPLICITLY, BECAUSE THE INHERITED DEFAULT IS THE WRONG ONE
     * HERE AND SILENCE WOULD LOOK IDENTICAL.** `AutopilotJob::claimIsSpent()`
     * defaults to keeping the claim, which protects a job whose irreversible act
     * is a message to somebody else's customer. **Nothing on this path reaches a
     * customer** — the one send is an email to the account holder about their own
     * account — and every failure before it is transient: a vendor timeout, a
     * mailer that could not be reached, a lost queue acknowledgement. Keeping the
     * claim through those would mean *"the owner is never told this conversation
     * ended"*, silently, which is the whole feature going quiet.
     *
     * ⛔ **SO IT IS RELEASED UNTIL THE NOTIFY HAS ACTUALLY GONE**, and held from
     * that moment, because a duplicate owner email is the one visible cost of a
     * retry. ⚠️ **A RETRY THAT GETS PAST THE AUDIT WRITE AND THEN FAILS LEAVES A
     * SECOND `agent.thread.closed` ROW** in an append-only log. That is a
     * duplicate of a true fact rather than a false one, and it is the affordable
     * side of the trade — the alternative is writing the audit entry after the
     * send, which loses the record of the close entirely whenever the mail fails.
     *
     * ## ⛔ *"UNTIL THE NOTIFY HAS ACTUALLY GONE"* IS FALSE TODAY, AND THE REPAIR IS REFUSED — 11147
     *
     * ⛔ **{@see PlatformMailer::send()} DISPATCHES INSIDE A `catch (Throwable)`
     * AND RETURNS `void`** (9500), so *"has actually gone"* means *"was pushed
     * at a queue, or was not, and this application cannot tell"* — and
     * {@see self::$notified} is set one line later either way. **So the claim
     * is spent on a message that never left, the run closes `Succeeded` with
     * `'notified' => true`, and the outcome is the one this method's own
     * paragraph above calls *the whole feature going quiet*.**
     * ⚠️ **This is `App\Jobs\Voice\NotifyOwnerOfVoicemailJob`'s defect, and
     * this is now the LAST UNREPAIRED SITE of the identical shape** — and that
     * job's own `claimIsSpent()` says it took this posture *from here*.
     *
     * ⛔ **THAT SENTENCE READ *"AT THE SECOND SITE"* AND WAS RIGHT ON THE DAY IT
     * WAS WRITTEN — CORRECTED 2026-08-28 (11308).** `App\Jobs\EscalateUrgentThreadJob`
     * was the second and was repaired between the two of them by 11140, so the
     * ordinal became three while nothing in this file moved. ⚠️ **It is not
     * restated as three**, because an ordinal in a docblock expires the next
     * time somebody repairs one (11277) — **the property is what to check**: of
     * the seven callers of {@see PlatformMailer::send()} in `app/`, this is the
     * only one that writes state asserting the message went.
     * ⚠️ **`App\Services\Billing\DunningNotices.php:220` is the near miss and
     * is deliberately not an instance** — it records
     * `DunningNoticeDelivery::Queued` rather than `Emailed` precisely because
     * `send()` cannot support the stronger word, and argues it at 10990.
     *
     * ⛔ **ITS REPAIR (10986) IS `deliverNow()`, IT WAS BUILT AND MEASURED HERE,
     * AND IT IS REVERTED — IT PRE-EMPTS THE URGENT PAGE ON A `sync`
     * DEPLOYMENT.** {@see AgentThreadStates} dispatches
     * this job `->afterCommit()` from inside the state mutation, and
     * `AgentTurns::escalateAsUrgent()` moves the thread **before** it
     * dispatches `App\Jobs\EscalateUrgentThreadJob`. On `sync` — which
     * `PlatformMailer::send()` calls *"a legitimate small-deployment choice"* —
     * a throw here leaves the commit and takes the rest of that method with it.
     * **Driven**: with the active mailer's ceiling unstated, the only
     * `automation_runs` row for an inbound *"there is a gas leak"* was
     * `agent.thread_close_summary` **failed**; `agent.urgent_escalation` never
     * ran at all, so nobody was paged, the customer was told nothing, and the
     * one bell that rang named a close summary. ⛔ **That is strictly worse than
     * the silence this repair removes, on exactly the fault it exists to make
     * loud** (11140–11143).
     *
     * ⚠️ **AND THE HARM HERE IS SMALLER THAN THE VOICEMAIL JOB'S.** A bell still
     * rings today, because `send()` queues and
     * {@see DeliverPlatformMail::failed()} raises
     * `PlatformMailUndeliverable` — per mailer, naming no account, but it
     * rings. What is lost is *"the owner is never told THIS conversation
     * ended"*, silently, on the highest-volume of the three owner-mail jobs.
     *
     * ⛔ **WHAT THE REPAIR IS BLOCKED ON IS THE DISPATCH SITE, NOT THIS
     * METHOD.** Twelve tests in three files outside that slice redden on the
     * change, and every one of them is the coupling showing itself — adding a
     * deliverable mailer to their fixtures would make it invisible again (256).
     * **A slice that moves this owes `AgentThreadStates::summarise()` first.**
     */
    protected function claimIsSpent(): bool
    {
        return $this->notified;
    }

    /**
     * @return array<string, mixed>
     */
    protected function execute(): array
    {
        $conversation = Conversation::query()->find($this->conversationId);

        if (! $conversation instanceof Conversation) {
            return ['notified' => false, 'reason' => 'thread_not_found'];
        }

        $summary = app(ThreadCloseSummaries::class)->summarise($conversation, $this->status);

        // ⛔ **THE FACT, WITHOUT THE SENTENCE.** See the class docblock.
        app(AuditService::class)->record(
            action: 'agent.thread.closed',
            actor: 'assistant',
            entity: $conversation,
            metadata: [
                // ⚠️ **`thread_status`, NOT `agent_status`, AND THE NAME IS THE
                // POINT** — `AnswerAgentTurnJob`'s own note, for its reason.
                // P3's chokepoint lint refuses any file outside
                // `AgentThreadStates` that writes an `agent_*` column, and it
                // matched this metadata key, correctly, because a lint cannot
                // tell a report from an assignment. Renaming keeps the lint
                // sharp instead of widening its allowlist, which is the change
                // that would quietly admit a real second writer later.
                'thread_status' => $this->status->value,
                'summary_from_model' => $summary->fromModel,
                'summary_fallback_reason' => $summary->fallbackReason,
            ],
        );

        $notified = $this->notifyOwner($summary);

        return [
            'notified' => $notified,
            'from_model' => $summary->fromModel,
            'fallback_reason' => $summary->fallbackReason,
            'thread_status' => $this->status->value,
            // ⛔ **THE SENTENCE IS NOT ON THE RUN ROW EITHER.** `automation_runs`
            // is operator-visible and long-lived, and this is prose about a real
            // customer's conversation.
        ];
    }

    /**
     * ⛔ **THE SAME WORK, ON DECISION 5411's REMEDY AND ITS TEST OF
     * APPLICABILITY.** *"Does this path touch the Google Business Profile
     * API?"* — it does not: the summary is our own model router, the notify is
     * our own mailer, the record is our own audit log. There is no
     * reduced-capability version of this job to describe, so `29` §2 rule 44 is
     * demonstrated here rather than excepted, exactly as it is by
     * {@see EscalateUrgentThreadJob}, {@see SendAgentNudgeJob} and
     * {@see SendMissedCallTextBackJob}.
     *
     * ⛔ **WHAT WAS HERE BEFORE WAS FORTY LINES NOTHING COULD SELECT.**
     * {@see AutopilotJob::handle()} chooses the arm on `! $this->canExecute()`;
     * the base returns `true` and this class does not override it, so
     * `handoff()` had never run and could not — a paused, suspended or
     * kill-switched tenant is `recordSkip()`ed **before** either arm is
     * reached. ⚠️ **THE HONEST STATEMENT OF THAT IS NARROWER THAN IT LOOKS,
     * AND THE OVERSTATEMENT IS THE THING TO AVOID**: rule 44 says only that
     * every automation implements both arms, and it says nothing about
     * reachability, so the old body did not violate it. **What it violated was
     * 314–316** — its own docblock read *"rule 44 satisfied with something
     * real"* about a branch nothing could run, and a paragraph claiming a
     * protection layer is what stops the next reviewer looking for one.
     *
     * ⛔ **AND IT WOULD HAVE BEEN WRONG THE DAY IT WAS SELECTED, WHICH IS THE
     * PART A DELEGATION FIXES RATHER THAN MERELY TIDIES.** It minted its own
     * `ThreadCloseSummary::factual('This conversation ended.',
     * 'assistant_unavailable')` — one sentence for all three of the statuses
     * rail 9 dispatches. ⛔ **AND THAT LINE IS THE ONLY THING SEPARATING TWO OF
     * THEM**: {@see AssistantThreadClosed} gives `Escalated`
     * and `TurnCapped` the same subject and the same greeting and *"differ only
     * in the body line"*, which is the sentence this arm overwrote. So a mail
     * from it would have opened *"A conversation needs you"* and then said
     * *"This conversation ended."* two lines below — the two writers
     * contradicting each other inside one message, which is exactly the defect
     * 7461 fixed in that notification, reintroduced from the other side.
     * ⚠️ **`fallback_reason` would have been false too** — the arm is
     * selected on `canExecute()`, which on this job could only ever be about
     * something other than the model, and `'assistant_unavailable'` asserts a
     * vendor outage that nothing had checked.
     *
     * ✅ **THE DEGRADATION THE OLD BODY WAS IMITATING ALREADY EXISTS, ONE LAYER
     * DOWN, AND IS STATUS-AWARE.** {@see ThreadCloseSummaries::summarise()}
     * cannot return nothing: a withheld covered entity, a thread with no logged
     * consent, an empty transcript, a broken CSPRNG, an unusable router, an
     * exhausted balance and a guardrail refusal all end in
     * {@see ThreadCloseSummaries::factualLine()}, which answers *per status* and
     * carries the reason that distinguishes them. Delegating therefore keeps
     * every property the old arm claimed — no vendor needed, the audit entry
     * written, the owner told — and drops only the wrong sentence.
     *
     * ⚠️ **IF A PROVIDER DEPENDENCY IS EVER ADDED TO THIS PATH**, the split
     * belongs back, together with the `canExecute()` override that can select
     * it and a test that reaches it. `ConventionsTest`'s *"no autopilot job
     * hides different rule-44 behaviour behind an arm nothing can select"* is
     * what fails the build if it comes back without one.
     *
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        return $this->execute();
    }

    /**
     * `FirstWeekPath::notifyOwner()`'s shape and its refusals.
     */
    private function notifyOwner(ThreadCloseSummary $summary): bool
    {
        $owner = Business::query()->find(Tenancy::idOrFail())?->owner;

        if (! $owner instanceof User) {
            return false;
        }

        $address = trim((string) $owner->email);

        if ($address === '') {
            return false;
        }

        // ⛔ **`send()` AND NOT `deliverNow()`, AND THE FLAG BELOW IS SPENT ON
        // A MESSAGE THIS APPLICATION CANNOT SAY WENT — 11147.** The repair is
        // `deliverNow()`, it is 10986's at the **last unrepaired** site of the
        // identical shape — *"the second"* until 11308, and the ordinal is
        // deliberately not restated; see `claimIsSpent()`'s docblock for the
        // property that replaces it — it was built and driven, and it is
        // **refused because it pre-empts the urgent page on a `sync`
        // deployment**. Read `claimIsSpent()`'s docblock before changing this
        // line; the change belongs with `AgentThreadStates::summarise()`, not
        // here.
        app(PlatformMailer::class)->send($address, new AssistantThreadClosed($summary, $this->status));

        // From here a retry would mail the owner twice — see `claimIsSpent()`.
        $this->notified = true;

        return true;
    }

    /**
     * ⛔ **SILENCE, ALWAYS — AND THAT IS A CHANGE (7460).**
     *
     * This method used to return {@see AutopilotActionType::AssistantFinishedAConversation}
     * on every run that found its conversation, **without ever reading
     * `$this->status`** — while rail 9 dispatches this job on all three of
     * resolved, escalated and capped. So an escalated thread, which is live and
     * has a member of the public waiting on the owner, got *"Your assistant
     * asked you to take a conversation"* and then, one row later, *"Finished a
     * conversation for you."* A capped thread got the same after *"A long
     * conversation was handed to you."* And a resolved one got
     * *"Finished a conversation for you"* **twice**, because
     * {@see AgentThreadStates::close()} files that case for the same event.
     * That is `29` §12.1's fabricated activity on two of the three statuses
     * and a duplicate on the third, and
     * {@see AutopilotActionType::AssistantFinishedAConversation}'s own docblock
     * forbade it in as many words the whole time: *"Merging them would tell an
     * owner a conversation had finished when somebody is waiting on them."*
     *
     * ⛔ **THE REMEDY IS NOT A STATUS-AWARE CASE HERE, AND 4541 IS WHY.** The
     * sibling {@see AnswerAgentTurnJob::activityAction()} faced this exactly and
     * answered *"return null always: it used to write the handover item itself,
     * which with a real state change behind it would put two feed items in for
     * one event."* The same is true here and is true on **all three** statuses,
     * not two: {@see AgentThreadStates} has already filed one row per close
     * before this job is dispatched — `close()` files
     * `AssistantFinishedAConversation`, `escalate()` files
     * `AssistantAskedYouToTakeOver` and `recordTurn()` files
     * `AssistantReachedItsTurnLimit`, each inside `mutate()`'s row lock, each
     * gated on `$changed` so a second close writes nothing, and each with the
     * `needsOwner()` answer its own event deserves. **This job's row was never
     * the only row for its event**, so making it a fourth correct sentence
     * would still be a second row for one thing that happened.
     *
     * ⚠️ **WHAT IS GIVEN UP IS `metadata['summary']`, AND IT IS NOT A LOSS.**
     * The feed template says so in capitals — *"METADATA IS NEVER RENDERED, IN
     * WHOLE OR IN PART"* — and a grep of `app/` and `resources/` finds no
     * reader of that key at all. **The summary reaches the owner by mail**,
     * through {@see self::notifyOwner()}, on a path that never touches a feed
     * row; the only consumer the key ever had was one test assertion, which now
     * pins the opposite property (that a fallible characterisation of a real
     * conversation stays off the feed) rather than being deleted.
     *
     * ⚠️ **THE `thread_not_found` ARMS ARE STILL WHY THE RETURN IS NULLABLE
     * — 7324, AND IT STAYS NULLABLE FOR THEM AS MUCH AS FOR THIS.** A method
     * typed `: AutopilotActionType` has given itself no way to stay silent, and
     * `ConventionsTest`'s second lint is what checks for that shape. Widening
     * this back would fail the build before it could fabricate anything.
     */
    protected function activityAction(): ?AutopilotActionType
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        return parent::input() + [
            'conversation_id' => $this->conversationId,
            'thread_status' => $this->status->value,
        ];
    }
}
