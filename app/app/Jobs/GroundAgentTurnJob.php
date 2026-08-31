<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AutopilotActionType;
use App\Enums\MessageDirection;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Agent\AgentGrounding;
use App\Services\Agent\AgentThreadStates;
use App\Services\Ai\AiSpend;

/**
 * Assembles what the assistant's next turn on a thread is grounded on — T176
 * §2.3 rails 1–3, patch P3.
 *
 * ⚠️ **QUEUED IS A RULE HERE, NOT A PERFORMANCE CHOICE** —
 * `IngestKnowledgeSourceJob`'s note, and for the same mechanism. Retrieval
 * embeds the question before it can compare it, so grounding is a vendor call,
 * and `29` §2 forbids an LLM call on the synchronous path. `ArchitectureTest`
 * enforces it structurally: `AiRouter` may not be named under
 * `Http/Controllers`, `Http/Middleware`, `Livewire` or `View/Components`, so a
 * screen can only dispatch this.
 *
 * An {@see AutopilotJob} rather than a plain queued job, for that class's
 * reasons exactly: it supplies the tenant a queued job would otherwise lack, our
 * kill switch, the tenant's own pause, the compliance suspension, the
 * idempotency key, backoff with jitter, and a run row that records the outcome
 * whatever it was. `29` §2 rule 40 asks for all of it.
 *
 * ## ⛔ IT DOES NOT ANSWER, AND IT DOES NOT SPEND A TURN
 *
 * There is no reply here and no send: the skills, the refusal set, the
 * disclosure and the outbound lint pass are **P4's**, and the composer and the
 * permit are already elsewhere. This job's whole output is the grounding, and it
 * is deliberately not `recordTurn()`'s caller — a turn is counted **when the
 * turn is taken**, which is when a reply is written, and counting one here would
 * burn rail 3's cap on a thread nothing answered. Whoever builds P4 calls
 * {@see AgentThreadStates::recordTurn()} beside the send.
 *
 * ## ⛔ IT HAS NO DISPATCHER, AND SINCE 4542 THAT IS A DECISION RATHER THAN A GAP
 *
 * ⚠️ **THIS SECTION WAS WRONG ON ITS FACTS AND RIGHT ABOUT ITS SHAPE.** It read
 * *"nothing in `app/` creates a {@see Conversation} at all"*, which stopped being
 * true when P18 landed `ConversationThreads::openFor()` — CLAUDE.md 2505's shape
 * inside a paragraph warning about 272's, the same correction 4535 made one file
 * over. The inbound → thread path is live and the Inbox reads it.
 *
 * ⛔ **WHAT IS TRUE IS THAT NOTHING DISPATCHES *THIS*, AND NOTHING SHOULD.**
 * {@see AnswerAgentTurnJob::execute()} calls
 * {@see AgentGrounding::forNextTurn()} **inline**, on the same thread and the
 * same question, and then uses the snippets. This job calls it and throws the
 * snippets away — its whole output is a count. So dispatching it beside the
 * answer would embed one customer's question twice and debit the AI pool twice
 * for a result nothing reads, which is 3297's *"a path with no debit does not
 * look uncapped, it looks free"* with the sign flipped: a debit with no
 * consumer.
 *
 * ⚠️ **IT IS KEPT RATHER THAN DELETED** because it is the honest shape for the
 * *other* caller T176 §2 implies and P18 did not build — a screen that wants a
 * thread grounded ahead of a person answering it, without taking a turn. That
 * caller does not exist today, and this paragraph is what stops the next reader
 * inferring one from an empty run table.
 *
 * ## Spend
 *
 * ⚠️ **NO NEW COST PATH IS OPENED HERE.** The only money this job can spend is
 * the embedding, and it is spent through `KnowledgeRetriever` → `AiRouter`,
 * which already asks {@see AiSpend::allows()} before the provider and records
 * the cost after it. `canExecute()` asks the same question first so that the
 * *run row* names the reason rather than leaving an empty result that reads like
 * a business with no documents — two states that must not look alike (3297: a
 * path with no debit does not look uncapped, it looks free).
 *
 * ⚠️ **AN EXHAUSTED BALANCE DEGRADES, IT NEVER THROWS** (2904, rule 43's
 * surviving half). Refusal lands on {@see handoff()}, which is a real path and
 * not an apology: the owner gets the thread with no assistant context, which is
 * what a business with no uploaded documents gets anyway.
 */
final class GroundAgentTurnJob extends AutopilotJob
{
    private int $snippetCount = 0;

    private bool $grounded = false;

    /**
     * ⛔ **THE ROW ID, NEVER THE WORDS — AND THIS PARAGRAPH USED TO SAY THE
     * OPPOSITE WHILE CONCEDING IT IN THE SAME SENTENCE** (8720-8724). It read:
     * *"`$question` IS UNTRUSTED AND IS NEVER STORED BY THIS JOB. Rail 1:
     * inbound text is untrusted data. It rides the queue payload because that is
     * what a question is, and it reaches nothing but the embedding call — **no
     * feed item, no audit entry and no run row carries it**"*. It enumerated the
     * three stores this class writes and omitted the two the **queue** writes,
     * `jobs.payload` and `failed_jobs.payload` — neither of which has row-level
     * security and neither of which any erasure reaches.
     * {@see AnswerAgentTurnJob} carries the measurement and the full
     * argument; this is the same defect in the same words.
     *
     * ⛔ **IT IS FIXED HERE EVEN THOUGH THIS JOB IS DARK, AND THAT IS
     * {@see self::canExecute()}'s OWN ARGUMENT ONE ROW OVER** (4542): *"the day
     * somebody gives this a dispatcher they inherit the refusal instead of
     * having to remember it, which is exactly what did **not** happen for the
     * answer path."* A dark job whose docblock states a property it does not
     * have ships that property to whoever lights it.
     *
     * ⚠️ **WHAT SURVIVES OF THE OLD PARAGRAPH IS RAIL 1 AND IT IS UNCHANGED**:
     * the body is untrusted, it reaches nothing but the embedding call, and no
     * feed item, audit entry or run row carries it — which is still why
     * {@see input()} omits it and {@see idempotencyKey()} is not keyed on it.
     * `PromptFence` is P4's and applies where the text meets a prompt, which is
     * not here.
     *
     * @param  int  $messageId  The `messages` row this turn grounds against.
     *                          ⚠️ **NO CALLER MINTS ONE TODAY**, deliberately —
     *                          see the class docblock. The caller T176 §2 implies
     *                          is a screen grounding a thread ahead of a person
     *                          answering it, and that caller has the row in hand
     *                          exactly as `InboundThreading` does.
     */
    public function __construct(
        int $businessId,
        ?int $locationId,
        public readonly int $conversationId,
        public readonly int $messageId,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return 'agent.ground_turn';
    }

    /**
     * ⚠️ **KEYED ON THE THREAD AND ITS TURN COUNT, NOT ON THE MESSAGE.** A
     * carrier or queue redelivery of the same inbound message must be one unit
     * of work; the *next* question on the same thread arrives after a turn has
     * been taken, so the count is what makes it genuinely new. Keying on the
     * question text would put a customer's own words into
     * `automation_runs.idempotency_key`, which is a store this application does
     * not put message bodies in — and since 8720 this job is not handed them at
     * all, so such a key would have to read the row back out first.
     */
    protected function idempotencyKey(): string
    {
        $turnsUsed = Conversation::query()
            ->whereKey($this->conversationId)
            ->value('agent_turns_used');

        return 'agent-ground:'.$this->conversationId.':'.(is_numeric($turnsUsed) ? (int) $turnsUsed : 0);
    }

    /**
     * ⚠️ FALSE UNLESS GROUNDING ACTUALLY HAPPENED, so a vendor outage can be
     * retried. `IngestKnowledgeSourceJob`'s reasoning: the base class keeps the
     * claim by default, which is right for a job whose side effect is a message
     * to somebody's customer and wrong for one that produces context.
     */
    protected function claimIsSpent(): bool
    {
        return $this->grounded;
    }

    /**
     * ⛔ **`isEnabled()` IS DELIBERATELY NOT OVERRIDDEN, AND THAT IS AN ARGUMENT
     * RATHER THAN AN OMISSION.** `29` §2 rule 40 asks for a toggle, and the four
     * gates that exist already run in `handle()`: our kill switch, the tenant's
     * own pause, the compliance suspension, and the balance below. **There is no
     * per-assistant toggle in this schema today** — T176 §2.4's on/off switches
     * are per skill (quotes, review-ask, nudge) and land with P4. Writing one
     * here that reads a key nothing can turn off would be a toggle that passes
     * vacuously (256), which is worse than none: it reads as a control somebody
     * could use.
     *
     * ⛔ **THE PHI GATE IS FIRST, AND IT IS HERE *BECAUSE* NOTHING DISPATCHES
     * THIS JOB** (4534, 4542). Retrieval embeds the question before it can
     * compare it, so a covered entity's customer's own words would go to an
     * embedding provider we hold no BAA with — the same exposure
     * {@see AnswerAgentTurnJob} was gated for, one vendor over. Gating a dark job
     * looks like 256's vacuous lint and is the opposite of it: the day somebody
     * gives this a dispatcher they inherit the refusal instead of having to
     * remember it, which is exactly what did **not** happen for the answer path.
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
            // Scoped out, or gone. Not an error: the global scope and RLS both
            // answer "not this tenant's" the way a deleted row does, and a job
            // for a thread that is not ours must do nothing rather than guess.
            return ['skipped' => 'conversation_not_found'];
        }

        $state = app(AgentThreadStates::class)->stateFor($conversation);

        if (! $state->mayTakeTurn()) {
            // Rails 3 and 4, and the run row says which. ⚠️ THIS IS NOT THE ONLY
            // PLACE THE QUESTION IS ASKED — `AgentGrounding` asks it again
            // immediately below, on purpose: 398's shape is an outer guard that
            // refuses first, leaving the inner one unfalsifiable and then
            // deleted as redundant. This one exists to *name the reason* on the
            // run row; that one exists to make the refusal true.
            return [
                'grounded' => false,
                'reason' => 'agent_may_not_speak',
                'agent_status' => $state->status->value,
                'turns_remaining' => $state->turnsRemaining(),
            ];
        }

        $question = $this->customerMessage();

        if ($question === null) {
            // Beside the conversation check above and for its reason: both are
            // *"the subject of this job is gone"*, and a grounding run with
            // nothing to ground against would spend an embedding on a blank
            // string. `AnswerAgentTurnJob::customerMessage()` carries the
            // argument for the predicates.
            return ['skipped' => 'message_not_found'];
        }

        $snippets = app(AgentGrounding::class)->forNextTurn($conversation, $question);

        $this->snippetCount = count($snippets);
        $this->grounded = true;

        // ⛔ COUNTS AND IDS, NEVER SNIPPET TEXT. The run row is operator-visible
        // and long-lived; the tenant's own document text lives in
        // `knowledge_chunks` under the ingest gate that admitted it.
        return [
            'grounded' => true,
            'snippets' => $this->snippetCount,
            'turns_remaining' => $state->turnsRemaining(),
        ];
    }

    /**
     * The same thread, without a model.
     *
     * NOT A STUB and not an apology — `29` §2 rule 44 wants this path built in
     * the same ticket, and here it has real work: the thread is left in a state
     * a person can act on, with the reason recorded, rather than silently
     * looking like a business that has uploaded nothing. Rail 2 already makes an
     * ungrounded assistant say *"I'll get {Owner} to confirm"*, so the
     * unavailable path and the empty-corpus path converge on the same honest
     * behaviour — which is why this is a degradation and never a hard fail
     * (2904).
     *
     * ⛔ **AND A COVERED ENTITY IS NAMED SEPARATELY FROM AN OUTAGE** (4534),
     * `AnswerAgentTurnJob::handoff()`'s argument on the same row: one comes back
     * and the other is what this tenant *is*, and an operator reading
     * `ai_unavailable` for a permanent refusal chases the wrong thing.
     *
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        return [
            'grounded' => false,
            'reason' => 'ai_unavailable',
            'snippets' => 0,
        ];
    }

    private function conversation(): ?Conversation
    {
        return Conversation::query()->find($this->conversationId);
    }

    /**
     * The customer's own words, read back from the row rather than carried.
     *
     * ⚠️ **A SIBLING OF {@see AnswerAgentTurnJob::customerMessage()},
     * AND DELIBERATELY NOT EXTRACTED.** The two jobs share a base class that
     * knows nothing about conversations, so a shared helper would have to live
     * on it or in a new collaborator — either of which makes a **third** file
     * able to read `messages`, which is exactly what `Architecture/InboxTest`'s
     * allowlist exists to make expensive. Two argued readers already on that
     * allowlist is the cheaper of the two shapes.
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
        // ⛔ THE QUESTION IS DELIBERATELY ABSENT. `input()` is written to the run
        // row, and a customer's own words do not belong in an operator-visible
        // operations table. Since 8720 they are not on the payload either, so
        // this is the weaker of the two refusals rather than the only one.
        return parent::input() + [
            'conversation_id' => $this->conversationId,
            'message_id' => $this->messageId,
        ];
    }

    /**
     * Silence — always.
     *
     * `AutopilotJob`'s docblock names the automation *"whose only honest title
     * is 'checked something and found nothing'"* as the one that makes the feed
     * worse, and this is stronger than that: grounding is preparation, not an
     * outcome. An owner does not want a feed item every time their assistant
     * looked something up; what they want in the feed is the three events that
     * change who is answering, and {@see AgentThreadStates} writes those.
     */
    protected function activityAction(): ?AutopilotActionType
    {
        return null;
    }
}
