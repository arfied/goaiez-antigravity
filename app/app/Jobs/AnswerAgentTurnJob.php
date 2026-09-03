<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\MessageSender;
use App\Enums\AgentSkill;
use App\Enums\AgentThreadStatus;
use App\Enums\AutopilotActionType;
use App\Enums\MessageDirection;
use App\Enums\OutreachChannel;
use App\Enums\OutreachPurpose;
use App\Enums\SendRefusalReason;
use App\Livewire\Account\Inbox;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Modules\CAgent\Models\AgentRefusal;
use App\Modules\CAgent\Models\AgentTurn;
use App\Services\Agent\AgentComposer;
use App\Services\Agent\AgentGrounding;
use App\Services\Agent\AgentReplyDraft;
use App\Services\Agent\AgentSkills;
use App\Services\Agent\AgentThreadStates;
use App\Services\Agent\AgentTurns;
use App\Services\Agent\ReviewAskBridge;
use App\Services\Agent\ReviewAskOffer;
use App\Services\Agent\ThreadState;
use App\Services\Ai\AiSpend;
use App\Services\Consent\ConsentService;
use App\Services\Consent\SendPermit;
use App\Services\Conversations\InboundThreading;
use App\Services\Messaging\Outbound\OutboundMessage;
use App\Services\Messaging\Outbound\SendKey;

/**
 * Takes one agent turn on a thread — T176 §2, patch P4.
 *
 * The whole loop, in order: read the state (rails 3 and 4) → work out which
 * skills exist (R13) → ground the turn (rail 2) → write it (rails 1, 5, 6, 7) →
 * **count the turn** → ask for a permit → send.
 *
 * ## ⛔ THIS IS WHERE `recordTurn()` IS CALLED, AND P3 LEFT IT DELIBERATELY
 * UNCALLED FOR THIS JOB TO CALL
 *
 * `GroundAgentTurnJob`'s own docblock says why it is not the caller: *"a turn is
 * counted when the turn is taken, which is when a reply is written, and counting
 * one here would burn rail 3's cap on a thread nothing answered."* So it is
 * counted **after the draft exists and before the send**, and the ordering is the
 * contract's rather than a convenience:
 *
 *  - **Before the send**, because {@see AgentThreadStates::recordTurn()} is
 *    *"called when the turn is taken, not when it succeeds"* — a reply that
 *    failed to send still consumed a turn's worth of loop, and counting only
 *    successes is how a failing thread runs for ever.
 *  - **After the draft**, because a job that died in the composer wrote nothing
 *    and said nothing, and charging the cap for it would let a vendor outage
 *    silently exhaust twelve turns of a thread nobody was answering.
 *  - **Not before the permit**, and this is the one that looks wrong and is not:
 *    a turn refused by consent or suppression is still a turn the assistant
 *    took. Counting only permitted sends would let a suppressed number loop the
 *    model for ever with the cap reading zero.
 *
 * ⚠️ **AND IT IS ASKED WHETHER IT MAY SPEAK BEFORE ANY OF THAT.**
 * `recordTurn()` refuses to move the counter on a latched thread by itself
 * (*"a latched thread accruing turns would cap silently while a person was
 * answering"*), so the gate here is about not spending a model call rather than
 * about protecting the count — which the state object already protects.
 *
 * ## ⛔ NO SEND PATH OF ITS OWN
 *
 * 2970–2979 records a platform halt that stopped one sender out of two, because
 * that sender had built a path of its own. This one goes through
 * {@see ConsentService} for the permit and {@see MessageSender} for the send, so
 * the kill switch, the tenant pause, the complaint-rate trip, suppression, STOP
 * and the Do Not Call registers all apply without this class knowing they exist.
 *
 * ⚠️ **`Transactional`, AND THE T69 LAW LIVES ON THAT ONE ARGUMENT.** R23: *"the
 * missed-call text-back, every agent reply, and every reply in a thread a
 * customer opened … go out 24/7."* `ConsentService::stateRefusal()` returns
 * before any quiet-hour window for a purpose outside `isSubjectToDoNotCall()`, so
 * `Marketing` here would hold a reply to a customer's own question behind
 * recipient-local quiet hours. A test mutates this line.
 *
 * ⛔ **AND THE PURPOSE IS AN HONEST CLASSIFICATION.** The composer's rail 5
 * forbids offers, discounts and promotional language outright, so what this sends
 * is an answer to a message somebody sent us. `MissedCallTextBack` makes the same
 * argument for the same reason.
 *
 * ## ⚠️ THE CONSENT QUESTION IS THE OPEN ONE, AND THIS JOB REFUSES RATHER THAN
 * ANSWERING IT
 *
 * There is still no ruling in `docs/DECISIONS.md` that an inbound text is a
 * sending basis — 3102's finding, unchanged. So this asks for a permit like every
 * other sender and a contact with no consent record is refused, which today is
 * most of them. **Nothing here writes a `ConsentRecord`**, and nothing here may:
 * *"a basis is a ruling, not a judgement call."*
 *
 * ## ✅ IT IS DISPATCHED, AND THE PHI GATE LANDED IN THE SAME SLICE (4540)
 *
 * ⚠️ **THIS SECTION HAS BEEN WRONG TWICE AND BOTH CORRECTIONS STAY.** It first
 * said `conversations` had no writer, which stopped being true when P18 landed
 * (4535) — a docblock warning about writerless tables while describing one that
 * had gained a writer, 2505's shape inside 272's own warning. It then said
 * nothing dispatched this job, which was true until {@see AgentTurns} — called
 * from `InboundMessages` on the ordinary-text arm, after
 * {@see InboundThreading::thread()} has filed the message.
 *
 * ⛔ **THE PHI GATE THAT LANDED WITH THE DISPATCHER (4534) IS GONE — 2026-08-30
 * (12540).** A `private phiWithheld()` asked {@see AgentTurns} whether this
 * tenant's inbound message text could reach a provider at all; the owner
 * overrode `29` §2 rule 24 and its §12.1 line, so **it now can.**
 *
 * ⚠️ **THE ARGUMENT THAT PUT IT THERE IS KEPT BECAUSE IT NAMES WHAT CHANGED**:
 * the same tenant's inbound MMS (4166) and voicemail audio (4500) were refused
 * before a socket opened, so leaving text ungated would have been the
 * inconsistency. **All three refusals are removed in the same slice**, which is
 * the consistent reading of the ruling rather than the convenient one.
 *
 * ⚠️ **`GroundAgentTurnJob` IS STILL DARK AND IS NOW DELIBERATELY SO**, which is
 * a different claim from the one this file used to make. {@see self::execute()}
 * calls {@see AgentGrounding::forNextTurn()} inline, so dispatching that job
 * beside this one would embed the same question twice — a doubled AI debit for
 * an output nothing reads. Its own docblock carries the reasoning.
 */
final class AnswerAgentTurnJob extends AutopilotJob
{
    private bool $turnTaken = false;

    /**
     * Memoised, on `AnalyzeReviewJob`'s shape: {@see self::canExecute()} and
     * {@see self::handoff()} both ask, and two reads of one row would be two
     * chances to disagree inside one job.
     */
    /**
     * ⛔ **THE ROW ID, NEVER THE WORDS — AND THIS PARAGRAPH USED TO SAY THE
     * OPPOSITE WHILE CONCEDING IT IN THE SAME SENTENCE** (8720-8723). It read:
     * *"`$message` IS UNTRUSTED AND IS NEVER STORED BY THIS JOB. Rail 1. It
     * rides the queue payload because that is what a message is, and it reaches
     * the fenced prompt and nothing else — **no run row, no feed item and no
     * audit entry carries it**"*. Every clause of that was true and the
     * enumeration was the wrong one: it named the three stores this class
     * writes and omitted the two the **queue** writes, four words after
     * admitting the payload is where the sentence rides. Measured rather than
     * argued — a dispatch on the `database` driver put
     * `s:7:"message";s:38:"…"` into `jobs.payload` in cleartext, and
     * `AutopilotJob` rethrows with `$tries = 3`, so the third failure copies it
     * into `failed_jobs.payload`. **Neither table has row-level security**
     * (`Architecture/TenancyTest`'s `$exempt`), **and no erasure reaches
     * either**: erasure here is delete-plus-survivors over rows (1380) and a
     * queue payload is not a row anybody can name.
     *
     * ⛔ **AND THE FIX WAS NOT A LONGER SENTENCE.** The words were already
     * durably filed by the same request — {@see InboundThreading::thread()} →
     * `ConversationThreads::recordInbound()` → the `messages` row, which is
     * `ENABLE`+`FORCE` row-level security and `cascadeOnDelete` on
     * `business_id`. The payload was a **second copy of a row that already
     * existed**, in the one store that survives an erasure request. So this
     * carries the id and reads the body back under RLS in
     * {@see self::customerMessage()}, which is what {@see EscalateUrgentThreadJob}
     * had already done on the sibling arm of this same path: *"⛔ THE WORDS,
     * NEVER THE MESSAGE. The customer's text is not on this payload and may not
     * be."*
     *
     * ⚠️ **WHAT SURVIVES OF THE OLD PARAGRAPH IS RAIL 1 AND IT IS UNCHANGED**:
     * the body is untrusted, it reaches the fenced prompt and nothing else, and
     * no run row, feed item or audit entry carries it — which is still why
     * {@see input()} omits it and {@see idempotencyKey()} is not keyed on it.
     *
     * @param  int  $messageId  The `messages` row this turn answers, minted by
     *                          the writer and carried through
     *                          `FiledInboundMessage`. ⚠️ **A BARE NAME AND NOT
     *                          AN `@see`, ON `PruneFailedJobs`' OWN NOTE**:
     *                          Pint's `fully_qualified_strict_types` hoists a
     *                          namespaced docblock name into a real `use`, and
     *                          an import this class does not use is code added
     *                          by a formatter.
     *                          ⛔ **NOT DERIVED HERE AS "THE LATEST INBOUND
     *                          MESSAGE ON THIS THREAD"**, which is the tempting
     *                          shape and is an inference: two texts on one
     *                          thread arriving a second apart dispatch two
     *                          jobs, and both would answer the newer one.
     * @param  string  $occasion  What made this turn happen — the inbound
     *                            message's own identity, minted by the caller.
     *                            ⛔ **PASSED IN RATHER THAN DERIVED, AND IT IS
     *                            `CallMissed::occasion()`'s ARGUMENT VERBATIM**:
     *                            *"two listeners deriving the occasion of this
     *                            missed call independently will eventually derive
     *                            it differently, and the day they do, a
     *                            redelivered webhook sends the caller two
     *                            apologies."* See {@see idempotencyKey()} for the
     *                            defect that made this a parameter.
     */
    public function __construct(
        int $businessId,
        ?int $locationId,
        public readonly int $conversationId,
        public readonly int $messageId,
        public readonly string $occasion,
        public readonly bool $hasInboundMedia = false,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return 'agent.answer_turn';
    }

    /**
     * ⛔ **KEYED ON THE OCCASION, AND THE FIRST VERSION KEYED ON THE TURN COUNT
     * AND WAS WRONG (4139).** `GroundAgentTurnJob` keys on the count and is right
     * to: grounding has no side effect, so a redelivery that recomputes the key
     * and runs again costs an embedding and nothing else. **This job sends a
     * message**, and the difference is decisive:
     *
     *  1. the job takes a turn, so `agent_turns_used` moves;
     *  2. a queue redelivery recomputes the key against the *new* count;
     *  3. the key is different, so nothing recognises it as a duplicate;
     *  4. a second turn is taken and **a second message is sent to the customer**.
     *
     * ⛔ **AND THE SEND KEY DID NOT CATCH IT EITHER**, which is what made it
     * worth fixing rather than tolerating: that was keyed on the same moving
     * count, so both dedupes moved together and neither was a backstop for the
     * other. Found by *a redelivery of the same turn is one unit of work* going
     * red with two turns taken.
     *
     * ⚠️ **THE OCCASION IS STABLE ACROSS REDELIVERIES BECAUSE IT IS A
     * CONSTRUCTOR ARGUMENT** — the queue serialises it and hands the same string
     * back on every attempt, where a database read hands back whatever is true
     * now.
     */
    protected function idempotencyKey(): string
    {
        return 'agent-answer:'.$this->conversationId.':'.$this->occasion;
    }

    /**
     * ⚠️ **THE CLAIM IS SPENT ONCE A TURN WAS COUNTED, NOT ONCE A MESSAGE
     * LANDED.** The counter has moved by then, so a retry would compute a
     * *different* idempotency key anyway and take a second turn against the cap.
     * Holding the claim from that moment is what makes the two agree.
     */
    protected function claimIsSpent(): bool
    {
        return $this->turnTaken;
    }

    /**
     * ⛔ **THERE IS STILL NO PER-ASSISTANT TOGGLE AND ONE IS STILL NOT INVENTED
     * HERE.** `GroundAgentTurnJob` made this argument and P4 does not weaken it:
     * §2.4's switches are **per skill** and live on `assistant_briefs`, where
     * {@see AgentSkills} reads them — a skill a business switched off is absent
     * from the briefing, which is a real control with a real test. A key here
     * that nothing can turn off would pass vacuously (256) while reading as a
     * control somebody could use. The four gates that do exist run in `handle()`:
     * our kill switch, the tenant's pause, the compliance suspension, and the
     * balance below.
     *
     * ⛔ **AND THE PHI GATE IS FIRST, BECAUSE IT IS THE ONE REFUSAL HERE THAT IS
     * NOT ABOUT AVAILABILITY** (4534). An exhausted balance is a thing that comes
     * back; a covered entity is what this tenant *is*, and rule 24's separate
     * schema, role and KMS key wait on Stage 3. Asked before the balance so the
     * run row names the condition an operator can do nothing about rather than
     * the one they can — `AutopilotJob::handle()`'s own ordering argument about
     * the suspension and the pause.
     */
    protected function canExecute(): bool
    {
        return app(AiSpend::class)->allows();
    }

    /**
     * @return array<string, mixed>
     */
    protected function execute(): array
    {
        $conversation = $this->conversation();

        if (! $conversation instanceof Conversation) {
            return ['skipped' => 'conversation_not_found'];
        }

        $message = $this->customerMessage();

        if ($message === null) {
            // ⚠️ **BESIDE THE CONVERSATION CHECK, NOT AFTER THE RAILS.** Both
            // are *"the subject of this job is gone"*, and they answer for the
            // same reasons — scoped out, cascade-deleted with the tenant, or an
            // id that never named a row. Putting it here costs one query on a
            // latched thread and keeps the two existence checks in one place;
            // putting it below would let a missing row be reported as a rail-4
            // refusal, which is a different fact about a different cause.
            return ['skipped' => 'message_not_found'];
        }

        $threads = app(AgentThreadStates::class);
        $state = $threads->stateFor($conversation);

        if (! $state->mayTakeTurn()) {
            // Rails 3 and 4. ⚠️ **ASKED HERE AND AGAIN INSIDE `AgentGrounding`,
            // ON PURPOSE** — 398's shape is an outer guard that refuses first,
            // leaving the inner one unfalsifiable and then deleted as redundant.
            // This one names the reason on the run row; that one makes the
            // refusal true.
            return [
                'answered' => false,
                'reason' => 'agent_may_not_speak',
                // ⚠️ **`thread_status`, NOT `agent_status`, AND THE NAME IS THE
                // POINT.** P3's chokepoint lint refuses any file outside
                // `AgentThreadStates` that writes an `agent_*` column, and it
                // matched this run-row key — correctly, because a lint cannot
                // tell a report from an assignment. Renaming keeps the lint
                // sharp instead of widening its allowlist, which is the change
                // that would have quietly admitted a real second writer later.
                'thread_status' => $state->status->value,
            ];
        }

        $skills = app(AgentSkills::class)->forThread($conversation, $this->hasInboundMedia);
        $snippets = app(AgentGrounding::class)->forNextTurn($conversation, $message);

        // ⛔ **MINTED ONLY WHEN SKILL 13 IS LIT, AND THE ORDER IS THE POINT
        // (P12).** `AgentSkills` already asked `ReviewAskBridge::groundedFor()`,
        // which mints nothing; this is the one call that writes a `short_links`
        // row, and gating it on the skill is what stops a token being minted per
        // turn for every business that switched the ask off.
        $reviewAsk = $skills->has(AgentSkill::ReviewAsk)
            ? app(ReviewAskBridge::class)->offerFor($conversation)
            : null;

        $composer = app(AgentComposer::class);

        $draft = $composer->write(
            customerMessage: $message,
            // ⛔ **THE THREAD ITSELF, BECAUSE R14 MINTS A SHORT LINK PER SEND**
            // (4271). The composer needs it to key the booking token to this
            // conversation's contact; it is the row this job already loaded under
            // the tenant scope, so the registry's cross-tenant refusal is a
            // backstop here rather than a gate.
            conversation: $conversation,
            skills: $skills,
            snippets: $snippets,
            // §2.1: the disclosure rides the first agent turn of the thread.
            // Read off the state that was taken before the turn was counted,
            // which is the only reading that is true at the moment the message
            // is written.
            isFirstAgentTurn: $state->turnsUsed === 0,
            reviewAsk: $reviewAsk,
        );

        // ⛔ **THE TURN IS COUNTED HERE — SEE THE CLASS DOCBLOCK FOR WHY THIS
        // LINE AND NOT ONE OF THE THREE OTHER PLAUSIBLE PLACES.** A draft exists,
        // so the loop ran; the send has not happened, so a refusal downstream
        // still costs a turn.
        //
        // ⚠️ **THE STATE IT RETURNS IS THE STATE *AFTER* THE COUNT**, which is
        // the one the escalation below has to be judged against — see there.
        $after = $threads->recordTurn($conversation);
        $this->turnTaken = true;

        $outcome = $this->send($conversation, $draft);

        if ($draft->fallbackReason !== null) {
            AgentRefusal::create([
                'business_id' => $conversation->business_id,
                'refusal_code' => $draft->fallbackReason,
                'reason' => 'Draft refused with fallback: '.$draft->fallbackReason,
                'user_input' => $message,
            ]);
        }

        AgentTurn::create([
            'business_id' => $conversation->business_id,
            'conversation_id' => $conversation->id,
            'turn_number' => $after->turnsUsed,
            'user_message' => $message,
            'agent_reply' => $draft->body,
            'status' => 'answered',
            'refusal_code' => $draft->fallbackReason,
        ]);
        // ⛔ **THE ASK IS SPENT AFTER THE SEND AND ONLY IF THE MESSAGE ACTUALLY
        // CARRIED THE LINK (P12).** Three conditions, and every one of them has
        // its own test: the send left, the body reproduced the URL, and there was
        // an offer at all. Recording on the offer alone would burn a business's
        // single ask on a turn where the model — correctly — decided the customer
        // was mid-question and asked for nothing.
        $reviewAskRecorded = false;

        if ($reviewAsk instanceof ReviewAskOffer
            && ($outcome['sent'] ?? false) === true
            && $reviewAsk->appearsIn($draft->body)
        ) {
            $reviewAskRecorded = app(ReviewAskBridge::class)->record($conversation, $reviewAsk) !== null;
        }

        $escalated = $this->escalate($threads, $conversation, $draft, $after);

        return [
            'answered' => true,
            'escalated' => $escalated,
            'review_ask_offered' => $reviewAskRecorded,
            'from_model' => $draft->fromModel,
            'fallback_reason' => $draft->fallbackReason,
            'needs_owner' => $draft->needsOwner,
            'skills_lit' => count($skills->lit),
            'snippets' => count($snippets),
            // ⛔ COUNTS, REASONS AND HANDLES — NEVER THE MESSAGE OR THE REPLY.
            // The run row is operator-visible and long-lived; the words live on
            // the `outreach_messages` row the sender wrote, which is the one
            // record of them.
            'send' => $outcome,
        ];
    }

    /**
     * Hand the thread to the owner when the assistant could not answer it —
     * rail 9's *escalated*, and 4541.
     *
     * ⛔ **THIS IS THE CALLER `AgentThreadStates::escalate()` SHIPPED WITHOUT AND
     * NAMED IN ADVANCE**: *"the composer's needs-owner draft and the urgent-terms
     * path are where this belongs and neither writes state today."* Until it
     * existed, a rail-5 refusal and a model outage both sent the customer *"{the
     * business} will get back to you shortly"* and left the thread reading **"your
     * assistant is answering"** on the owner's own Inbox — a promise to a member
     * of the public with nothing anywhere asking anybody to keep it.
     *
     * ⛔ **AFTER THE SEND, NEVER BEFORE.** `escalate()` dispatches
     * `SummariseClosedThreadJob`, which mails the owner; doing it first would
     * announce a handover for a turn that then failed to send at all.
     *
     * ⛔ **AND ONLY FROM `AgentHandling`, WHICH IS THE HALF THAT LOOKS LIKE A
     * DETAIL AND IS NOT.** `recordTurn()` moves a thread that has just used its
     * last turn to `TurnCapped` and writes rail 9's cap summary itself. Escalating
     * on top would overwrite that status with `Escalated`, send a **second**
     * owner summary for one event, and lose the distinction the enum exists for —
     * *"a cap is the agent working correctly rather than a failure"*. So the two
     * rail-9 outcomes stay disjoint, and the state after the count is what tells
     * them apart.
     *
     * ⚠️ **A NEEDS-OWNER DRAFT ON AN ALREADY-ESCALATED THREAD MOVES NOTHING**, by
     * `escalate()`'s own idempotence — which is {@see AgentReplyDraft::$needsOwner}'s
     * documented intent: *"a static template on a thread the owner has already
     * been told about does not need a second ping."*
     */
    private function escalate(
        AgentThreadStates $threads,
        Conversation $conversation,
        AgentReplyDraft $draft,
        ThreadState $after,
    ): bool {
        if (! $draft->needsOwner || $after->status !== AgentThreadStatus::AgentHandling) {
            return false;
        }

        $threads->escalate($conversation);

        return true;
    }

    /**
     * Ask for a permit and send, or say why not.
     *
     * @return array<string, mixed>
     */
    private function send(Conversation $conversation, AgentReplyDraft $draft): array
    {
        $customerId = $conversation->customer_id;

        $customer = $customerId === null
            ? null
            : Customer::query()->find($customerId);

        if (! $customer instanceof Customer) {
            // ⛔ **NOBODY TO ADDRESS, AND NO CONTACT IS CREATED HERE.** A thread
            // with no contact is the ordinary state for a first-time caller, and
            // manufacturing a `Customer` in order to text them would store a
            // stranger's mobile on a basis nobody has established — the same
            // refusal `MissedCallTextBack` makes and for the same reason.
            return ['sent' => false, 'reason' => SendRefusalReason::NoIdentifier->value];
        }

        $decision = app(ConsentService::class)->decide(
            $customer,
            OutreachChannel::Sms,
            // ⛔ **THE T69 LAW IS THIS ARGUMENT.** See the class docblock. A test
            // mutates it to `Marketing` and goes red.
            OutreachPurpose::Transactional,
        );

        $permit = $decision->permit;

        if (! $permit instanceof SendPermit) {
            return [
                'sent' => false,
                'reason' => ($decision->reason ?? SendRefusalReason::NoConsentRecord)->value,
            ];
        }

        $outcome = app(MessageSender::class)->send(OutboundMessage::for(
            permit: $permit,
            body: $draft->body,
            // ⛔ **KEYED ON THE OCCASION, NOT ON THE TURN COUNT** — see
            // {@see idempotencyKey()}. Keying it on the count made this dedupe
            // move in lockstep with the one above, so neither could be the
            // other's backstop and a redelivery sent a second message. The
            // occasion is the same string on every attempt, so
            // `outreach_messages`' unique index arbitrates a redelivery and the
            // sender answers `Duplicate`, debiting nothing.
            key: SendKey::for($permit, 'agent-turn:'.$conversation->getKey().':'.$this->occasion),
            purpose: OutreachPurpose::Transactional,
        ));

        return [
            'sent' => $outcome->wasSent(),
            'send_key' => $outcome->key->value,
            // ⚠️ **THE SENDER'S OWN VERDICT, NOT JUST OURS.** A permit granted
            // and a message not sent are two different facts, and the first
            // version of this method reported only the second — so a run row
            // said `sent: false` with a valid send key beside it and nothing
            // naming the gate that refused. Every refusal downstream of the
            // permit (the platform halt, the tenant pause, the complaint-rate
            // trip, no sending number) lands on this field, and an operator
            // reading `sent: false` with no reason raises a ticket about the
            // wrong thing (3962's lesson, one layer over).
            'status' => $outcome->status->value,
            'refusal' => $outcome->reason?->value,
        ];
    }

    /**
     * The thread, without a model — rail 6 at the job level.
     *
     * ⚠️ **NOT A STUB AND NOT A DUPLICATE OF THE COMPOSER'S TEMPLATE.** The two
     * are different failures: the composer's fires when the *model* is
     * unreachable and still sends the standing line, because a customer is
     * waiting at the other end of a live thread. This one fires when
     * `AutopilotJob` refused the whole automation — our kill switch, the tenant's
     * pause, a compliance suspension, an exhausted balance — and in every one of
     * those cases **sending anything is the thing that has been forbidden**.
     *
     * ⛔ **SO IT SENDS NOTHING AND COUNTS NO TURN.** The thread is left where a
     * person can pick it up, which is what a paused tenant asked for. Rule 44
     * wants this path built in the same ticket; here its real work is refusing
     * without the thread looking answered.
     *
     * ⛔ **AND IT NAMES THE PHI REFUSAL SEPARATELY, WHICH IS 4500's ARGUMENT ON
     * THE RUN ROW** (4534): a tenant who was refused and a tenant whose vendor was
     * down must not read the same to whoever is looking. The owner-visible half of
     * that distinction is the thread state {@see AgentTurns} writes; this is the
     * operator-visible half.
     *
     * ⚠️ **IT DOES NOT ESCALATE THE THREAD ITSELF, AND THAT IS DELIBERATE.**
     * `AgentTurns::answer()` refuses a covered entity *before* dispatching and
     * escalates there, where the audit line naming the reason is also written; a
     * second escalation from here would be a second writer of the same event for
     * the ordinary path, and this path is reached only when something dispatched
     * around that gate.
     *
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        return [
            'answered' => false,
            'reason' => 'assistant_unavailable',
            'needs_owner' => true,
        ];
    }

    private function conversation(): ?Conversation
    {
        return Conversation::query()->find($this->conversationId);
    }

    /**
     * The customer's own words, read back from the row rather than carried.
     *
     * ⛔ **THREE PREDICATES, AND TWO OF THEM ARE NOT BELT AND BRACES.** The
     * global scope and RLS answer the tenant; what they do not answer is *which*
     * row. A wrong or stale id that happened to name another thread's message
     * would put one conversation's words into another conversation's prompt, and
     * one that named this job's **own outbound reply** would feed the assistant
     * its own text as the customer's question — so the thread and the direction
     * are both asserted rather than assumed. `Architecture/InboxTest` permits
     * this file to read the two tables and its allowlist entry argues why.
     *
     * ⚠️ **A BLANK BODY IS THE SAME ANSWER AS A MISSING ROW.** `messages.body`
     * is nullable, `AgentTurns::answer()` already refuses an empty text before
     * dispatching, and a turn with nothing to answer would spend an embedding
     * and a completion on a blank prompt.
     */
    private function customerMessage(): ?string
    {
        $body = Message::query()
            ->whereKey($this->messageId)
            ->where('conversation_id', $this->conversationId)
            ->where('direction', MessageDirection::Inbound->value)
            ->value('body');

        if (! is_string($body) || trim($body) === '') {
            return null;
        }

        return trim($body);
    }

    /**
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        // ⛔ THE MESSAGE IS DELIBERATELY ABSENT — see the constructor. It is no
        // longer on the payload either, so this is now the weaker of the two
        // refusals rather than the only one; it stays because `input()` is
        // written to an operator-visible run row and the words would not belong
        // there even if the job were handed them.
        return parent::input() + [
            'conversation_id' => $this->conversationId,
            'message_id' => $this->messageId,
        ];
    }

    /**
     * ⚠️ **SILENCE, ALWAYS.** `AutopilotJob` names *"checked something and found
     * nothing"* as the automation that makes the feed worse, and an owner does not
     * want a feed item for every text their assistant answered correctly — the
     * Inbox is where a conversation is read.
     *
     * ⛔ **AND THE HANDOVER ITEM IS NOT THIS JOB's EITHER, WHICH IS A CHANGE**
     * (4541). This method used to return `AssistantHandedOver` on a needs-owner
     * draft, written for a world where nothing moved the thread's state. Now
     * {@see self::escalate()} does, and `AgentThreadStates::escalate()` records
     * that same action in the feed and in `audit_log` — so returning it here as
     * well would put **two entries in the feed for one event**, which is the exact
     * argument `recordTurn()`'s docblock already makes about the turn-cap item.
     * ⚠️ **THE CASE NAMED ABOVE IS NO LONGER THE ONE WRITTEN — CORRECTED
     * 2026-08-22 (7420, on 7340–7354's work).** `AgentThreadStates::escalate()`
     * now files `AssistantAskedYouToTakeOver`, because `AssistantHandedOver`
     * says *"You took over a conversation"* about an event in which the owner
     * did nothing and `needsOwner()` answered `false` — so every escalation
     * reached the feed with no "Needs you" marker while somebody waited.
     * ⚠️ **THIS PARAGRAPH'S ARGUMENT SURVIVES WHOLE**: the item is still not
     * this job's, and returning one here would still put two entries in the
     * feed for one event. Only the case name is stale.
     * The feed content is unchanged; what changed is which class writes it, and
     * that it now comes with a state change and an audit row behind it.
     */
    protected function activityAction(): ?AutopilotActionType
    {
        return null;
    }
}
