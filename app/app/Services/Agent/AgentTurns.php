<?php

declare(strict_types=1);

namespace App\Services\Agent;

use App\Contracts\Agent\AgentThreads;
use App\Jobs\AnswerAgentTurnJob;
use App\Jobs\EscalateUrgentThreadJob;
use App\Jobs\GroundAgentTurnJob;
use App\Jobs\SummariseClosedThreadJob;
use App\Models\Conversation;
use App\Services\Assistant\UrgentTerms;
use App\Services\AuditService;
use App\Services\Conversations\FiledInboundMessage;
use App\Services\Conversations\InboundThreading;
use App\Services\Sms\InboundMessages;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The dispatcher the agent engine never had — T176 §2, and decision 4534.
 *
 * ## ⛔ WHAT WAS ACTUALLY MISSING WAS ONE DISPATCH
 *
 * P3, P4, P13, P18 and P19 built the whole conversation lane and **nothing in
 * `app/` dispatched any of it**: verified 2026-08-17, zero callers for
 * {@see AnswerAgentTurnJob} and {@see GroundAgentTurnJob}, and none for
 * {@see AgentThreads::close()} or {@see AgentThreads::escalate()} either — which
 * left {@see SummariseClosedThreadJob} reachable only from the turn cap.
 * CLAUDE.md's decision-272 shape across a feature rather than a column, and the
 * tell it names applied in full: **every isolation test in the lane passed
 * perfectly against a path nothing ran.**
 *
 * ⚠️ **`conversations` HAS HAD A WRITER SINCE P18 AND THAT IS WHY THIS IS SMALL**
 * (4535). {@see InboundMessages} threads ordinary inbound text through
 * {@see InboundThreading::thread()} and the Inbox
 * reads it. So the inbound → thread half was live; this is the thread → turn
 * half, and it is one call.
 *
 * ## ⛔ THE PHI GATE THAT LANDED IN THIS CLASS IS GONE — 2026-08-30 (12540)
 *
 * `withholdsFor()` and `refuseForHealthTenant()` refused a covered entity's
 * turn before it could reach a model, and 4534 argued them in. **The owner
 * overrode `29` §2 rule 24 and its §12.1 line**, so the refusal, its audit
 * reason and the escalation it triggered are all removed.
 *
 * ⛔ **WHAT THAT MEANS IN PLAIN WORDS, BECAUSE THE ORIGINAL ARGUMENT IS WORTH
 * KEEPING EVEN THOUGH ITS CONCLUSION MOVED**: 4166 refused a `Phi` tenant's
 * inbound MMS and 4500 refused their voicemail audio, on the reasoning that *a
 * photograph of a condition is refused and a sentence describing the same
 * condition is not*. **All three refusals are now gone together**, which is at
 * least consistent — the failure the argument warned about was one of them
 * surviving alone.
 *

 * ## ⛔ SKILL 9 IS DECIDED HERE, AND THAT IS THE SECOND THING THIS CLASS DOES
 *
 * ⚠️ **THIS SECTION IS NEW AND THE ONE BELOW IT IS NARROWER THAN IT READS**
 * (5400). {@see UrgentTerms::matches()} had **zero callers in `app/`** — 5323 —
 * so a tenant's urgent words lit skill 9 in the prompt and *the model* decided
 * what urgent meant. {@see self::escalateAsUrgent()} is the deterministic path
 * beside that instruction: it runs before the health gate and before any
 * dispatch, it escalates the thread synchronously, and
 * {@see EscalateUrgentThreadJob} pages the owner and answers the customer with a
 * static template. **No model is asked on that arm and no AI ledger is
 * debited.**
 *
 * ## ⛔ IT IS NOT THE SEND PERMIT, AND THE THREAD STATE IS THE EXCEPTION
 *
 * A dispatch here is *"somebody texted this business"* and nothing more. Consent,
 * suppression, STOP, the Do Not Call registers, the credit debit and the
 * `outreach_messages` row are all `MessageSender`'s, downstream and untouched;
 * rails 3 and 4 are {@see AgentThreads}', and this deliberately does not ask
 * them **on the arm that dispatches a turn** — see {@see self::answer()}.
 *
 * ⚠️ **THE TWO ARMS THAT DO *NOT* DISPATCH BOTH WRITE THREAD STATE, AND THEY
 * ASK OPPOSITE THINGS OF IT.** The health refusal escalates without reading the
 * state at all, because rule 24 is unconditional; skill 9 reads
 * `ThreadState::mayTakeTurn()` first, because escalating over a person's latch
 * would defeat rail 4. Neither is *"the stale half of the pair"* the paragraph
 * in {@see self::answer()} refuses, because both act on their read in the same
 * process microseconds later rather than handing it to a worker.
 *
 * ## ⛔ THE ARBITER IS NOT CONSULTED, AND THAT IS T69 RATHER THAN AN OMISSION
 *
 * §2's own sentence: *"T69 exempts its in-thread replies from quiet hours and the
 * S1 arbiter (service class — absent from the arbiter by design, already encoded
 * in `MarketingTouch`)."* There is no `MarketingTouch` case for a reply in a
 * thread a customer opened, so `SendCollisionArbiter` **cannot** be passed one —
 * the only way to hold a service message is to write code that does it
 * deliberately, which is that class's own design.
 */
final class AgentTurns
{
    /**
     * Why this message was handed to the owner instead of answered — skill 9.
     *
     * ⚠️ **ONE SPELLING, IN THE RUN ROW, THE AUDIT ROW AND THIS CLASS'S RETURN
     * VALUE**: three near-synonyms for one event is three things nobody can grep
     * for at once. ⚠️ **A `REFUSED_HEALTH_TENANT` constant sat beside this one
     * and made the same argument until 2026-08-30 (12540).**
     */
    public const string URGENT_TERM = 'urgent_term';

    /** Who the audit row names for a decision nobody in this company took. */
    public const string ACTOR = 'system:agent-turns';

    public function __construct(
        private readonly AgentThreads $threads,
        private readonly AuditService $audit,
        private readonly UrgentTerms $urgent,
    ) {}

    /**
     * Take the agent's next turn on a thread, or say why not.
     *
     * Returns **null when a turn was dispatched**, and the reason otherwise.
     *
     * ⛔ **IT DOES NOT ASK `ThreadState::mayTakeTurn()`, AND THAT IS THE ONE
     * REFUSAL IN THIS CLASS MOST LIKELY TO BE "FIXED" LATER.** Rails 3 and 4 are
     * answered *at the turn*, under the row lock, by
     * {@see AgentThreadStates::stateFor()} — whose own docblock says why: *"a
     * queued job holding a `Conversation` hydrated before the owner pressed send
     * would otherwise read `AgentHandling` and speak."* A check here is a read
     * taken seconds — or, on a backed-up queue, minutes — before the job runs, so
     * it can only ever be the *stale* half of the pair. Adding it would buy a
     * saved dispatch and cost the reader the impression that the latch is decided
     * here.
     *
     * ⚠️ **AND THE REFUSED RUN ROW IS WORTH HAVING.** A latched thread produces
     * an `automation_runs` row reading `agent_may_not_speak` with the status
     * beside it, which is the record an owner's question — *"why did my assistant
     * not answer that?"* — is actually answered from. A dispatch skipped here
     * would leave nothing anywhere.
     *
     * ⛔ **AND THE KILL SWITCH IS NOT ASKED EITHER**, though
     * `AutopilotJob::killSwitchThrownFor()` is public and a sweeper precedent
     * exists for asking it early. That precedent is about a sweep writing one
     * skipped row *per candidate per tick*; this is one inbound message at a
     * time, and the skipped row is the honest record that a customer texted in
     * while the automation was stopped.
     *
     * @param  FiledInboundMessage  $message  The thread, the `messages` row id
     *                                        and the body, from
     *                                        {@see InboundThreading::thread()}.
     *                                        ⛔ **PASSED IN RATHER THAN LOOKED
     *                                        UP** — the caller has all three in
     *                                        hand, and a lookup here would make
     *                                        this a second reader of
     *                                        `conversations`, which
     *                                        `Architecture/InboxTest` exists to
     *                                        prevent. ⛔ **AND THEY ARRIVE AS
     *                                        ONE OBJECT RATHER THAN AS THREE
     *                                        ARGUMENTS** (8721): the thread and
     *                                        the words used to be independent
     *                                        parameters, so nothing anywhere
     *                                        guaranteed the second was a message
     *                                        on the first.
     * @param  string  $occasion  What made this turn happen — the carrier's own
     *                            message id. {@see AnswerAgentTurnJob::$occasion}
     *                            requires it to be **stable across
     *                            redeliveries**, and a vendor handle is; a
     *                            derived value is not.
     */
    public function answer(
        FiledInboundMessage $message,
        string $occasion,
        bool $hasInboundMedia = false,
    ): ?string {
        $businessId = Tenancy::idOrFail();

        $thread = $message->thread;

        // ⚠️ **THE ONE LEAK NEITHER THE GLOBAL SCOPE NOR RLS CAN SEE** —
        // `AgentThreadStates::refuseForeignThread()`'s precedent and its
        // reasoning exactly: a caller can hand in a model hydrated under another
        // tenant, and dispatching on it would run one business's assistant
        // against another business's thread.
        //
        // ⚠️ **THIS SAID "WOULD PUT ONE BUSINESS'S CUSTOMER'S WORDS INTO
        // ANOTHER BUSINESS'S JOB PAYLOAD" AND THAT CONSEQUENCE IS GONE SINCE
        // 8720** — the payload carries a row id now. **The guard is unchanged
        // and what it prevents got worse rather than better**: the job would
        // load the foreign thread, read its `customer_id`, and text a member of
        // the public on behalf of a business that has never heard of them.
        // Restating the old consequence would understate it.
        if (! $thread->exists || (int) $thread->business_id !== $businessId) {
            throw new RuntimeException(
                'That conversation is not this tenant\'s, so the assistant cannot be asked to answer it.',
            );
        }

        $body = trim($message->body);

        if ($body === '') {
            // `InboundThreading` already refuses an empty body, so this is the
            // second reader of the same fact rather than the first. It is kept
            // because a turn with nothing to answer would spend an embedding and
            // a completion on a blank prompt.
            return 'empty_message';
        }

        // ⛔ **SKILL 9, DECIDED HERE AND NOT BY THE MODEL** (5323). See
        // {@see self::escalateAsUrgent()} for why this is the deterministic half
        // of a rule the prompt states, and why it is asked **before** the health
        // gate below.
        if ($this->escalateAsUrgent($thread, $body, $occasion)) {
            return self::URGENT_TERM;
        }

        $locationId = $thread->location_id;

        // ⛔ **THE ROW ID, NEVER THE WORDS** (8720-8723). The sentence a member
        // of the public wrote used to ride this call into the job's constructor,
        // which serialises it whole into `jobs.payload` and — after three
        // attempts — into `failed_jobs.payload`, neither of which has row-level
        // security and neither of which any erasure reaches. `messages` has
        // both. {@see EscalateUrgentThreadJob} refused the identical data on the
        // identical path for the identical reason and was right; this arm was
        // the one that did not.
        Log::warning('Dispatching AnswerAgentTurnJob for business '.$businessId);
        AnswerAgentTurnJob::dispatch(
            $businessId,
            is_numeric($locationId) ? (int) $locationId : null,
            (int) $thread->getKey(),
            $message->messageId,
            $occasion,
            $hasInboundMedia,
        );

        return null;
    }

    /**
     * Hand this message straight to the owner if it carries one of the
     * business's own urgent words — §2.2 skill 9, decisions 5323 and 5334(b).
     *
     * Returns **true when the thread was escalated**, in which case no turn is
     * dispatched and {@see EscalateUrgentThreadJob} owns everything after.
     *
     * ## ⛔ WHY A CALLER AT ALL
     *
     * {@see UrgentTerms::matches()} had **zero callers in `app/`**. The terms
     * lit skill 9 in {@see AgentSkills}, the skill put a sentence in the prompt,
     * and *the model decided what urgent meant*. 5323 records it as 272's shape
     * on a safety feature; 5334(b) records that closing it is a build. **This
     * line is the build.**
     *
     * ⛔ **AND IT REPLACES THE MODEL'S TURN RATHER THAN RIDING BESIDE IT**,
     * which is the decision in this method worth arguing with. Escalating first
     * and dispatching anyway is unbuildable — `Escalated` fails
     * `ThreadState::mayTakeTurn()`, so the job would refuse itself — and
     * escalating *after* the turn would make the page conditional on a
     * completion succeeding, an AI balance being funded and a vendor being up.
     * **A safety escalation that a vendor outage can silence is not one.** What
     * the customer loses is a model-composed answer to a message the owner has
     * already said means drop everything; §2.2 row 9 asks for *"tell them help
     * is being arranged and give the emergency line"* and that is exactly what
     * the deterministic reply says.
     *
     * ## ⚠️ IT WAS ASKED BEFORE THE HEALTH GATE, WHICH NO LONGER EXISTS
     *
     * The ordering mattered while a health gate refused a covered entity's text
     * before it reached a **model**; nothing on this path reaches one anyway —
     * the match is a regex against the tenant's own list, run in this process,
     * and the reply is a static template. Refusing it for a `Phi` tenant would
     * have denied the emergency line to precisely the businesses whose
     * out-of-hours messages most need
     * one. ⚠️ **The consequence is that a covered entity's urgent message is
     * audited as `urgent_term` rather than `refused_health_tenant`** — both
     * escalate the thread, no model is reached on either arm, and the reason
     * written down is the true one.
     *
     * ## ⛔ AND IT REFUSES A THREAD THE ASSISTANT MAY NOT SPEAK ON
     *
     * {@see ThreadState::mayTakeTurn()} is *"the whole gate"* and it is asked
     * here rather than reimplemented, because the four silences need four
     * different answers and a caller comparing statuses by hand forgets one.
     * **`HumanTakeover` is the case this exists for**: rail 4 says a person
     * answering owns the thread, and escalating over their latch would overwrite
     * *"you are answering"* with *"needs you"* and mail them about a
     * conversation they are reading. `TurnCapped` and `Escalated` are already
     * with the owner. In every one of those the message falls through to the
     * ordinary path, whose run row records `agent_may_not_speak` — 4534's
     * *"the refused run row is worth having"*.
     *
     * ⚠️ **THE READ IS NOT UNDER THE WRITE'S LOCK, AND THAT RACE IS INHERITED
     * RATHER THAN INTRODUCED.** {@see AnswerAgentTurnJob::escalate()}
     * has exactly this shape — read the state, then escalate — and the window is
     * the milliseconds between them. `escalate()` is idempotent and refuses an
     * escalated or closed thread itself; what it cannot refuse is a latch that
     * landed inside the window.
     */
    private function escalateAsUrgent(Conversation $thread, string $body, string $occasion): bool
    {
        $matched = $this->urgent->matches($body);

        if ($matched === []) {
            // ⚠️ **THE ORDINARY ANSWER, AND AN EMPTY LIST IS THE SAME ANSWER.**
            // `UrgentTerms` says it in its own docblock: an empty list switches
            // off skill 9's *trigger*, never the safety, and the life-safety
            // instruction is `AgentComposer`'s and unconditional.
            return false;
        }

        if (! $this->threads->stateFor($thread)->mayTakeTurn()) {
            return false;
        }

        // ⚠️ **THE AUDIT ROW IS WRITTEN BEFORE THE STATE MOVES**: when two
        // writes cannot both be relied on, the one that must survive goes
        // first. (The rule was `refuseForHealthTenant()`'s, removed with rule
        // 24 on 2026-08-30 — it holds here on its own terms.)
        //
        // ⛔ **THE COUNT, NEVER THE WORDS AND NEVER THE MESSAGE.** `audit_log` is
        // append-only and no erasure request reaches it cleanly, and *which* of
        // a tenant's words matched is an inference about what a member of the
        // public wrote. The owner is told the words in a mail they can delete;
        // the permanent record gets the fact that skill 9 fired.
        $this->audit->record(
            action: 'agent.turn_urgent',
            actor: self::ACTOR,
            entity: $thread,
            metadata: ['reason' => self::URGENT_TERM, 'terms_matched' => count($matched)],
        );

        $this->threads->escalate($thread);

        $locationId = $thread->location_id;

        // ⚠️ **DISPATCHED AFTER THE STATE MOVED, SO THE JOB CANNOT SPEAK AS THE
        // ASSISTANT.** Its message says the assistant has stopped; a worker that
        // picked it up before the escalation committed would say so of a thread
        // still reading `AgentHandling` on the owner's Inbox.
        EscalateUrgentThreadJob::dispatch(
            Tenancy::idOrFail(),
            is_numeric($locationId) ? (int) $locationId : null,
            (int) $thread->getKey(),
            $occasion,
            $matched,
        );

        return true;
    }
}
