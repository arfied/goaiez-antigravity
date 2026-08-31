<?php

declare(strict_types=1);

namespace App\Services\Agent;

use App\Contracts\Agent\AgentThreads;
use App\Contracts\Campaigns\CampaignContextResolver;
use App\Enums\AgentThreadStatus;
use App\Enums\AutopilotActionType;
use App\Jobs\SummariseClosedThreadJob;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\AuditService;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The agent core's thread state — T176 §2.3 rails 3 and 4, patch P3.
 *
 * The one implementation of {@see AgentThreads}. Its contract explains why there
 * may only be one: *"the thing most likely to break rail 4 is a second place
 * that sets the same column."* Everything below therefore reads and writes the
 * five `conversations.agent_*` columns and nothing else in the application does.
 *
 * ## Every write takes the row lock first
 *
 * ⚠️ **THE LATCH IS A RACE BY CONSTRUCTION.** The event it records — a person
 * pressing send in the Inbox — happens at the same moment as the thing it must
 * beat, a queued job about to take the agent's turn. `SELECT … FOR UPDATE`
 * inside a transaction is what makes *"the moment a human replies"* a single
 * serialised point rather than two readers of a stale value. `recordTurn()`
 * takes the same lock for the same reason and in the same order, so the two can
 * never deadlock against each other.
 *
 * ⛔ **AND THE LOCK IS WHY `stateFor()` IS READ ONCE PER TURN.** The contract
 * says so: re-reading between the permission check and the send re-opens exactly
 * the window the lock closes.
 *
 * ## What is deliberately not here
 *
 * ⛔ **NOTHING AUTOMATIC MAY RE-ARM**, and `rearm()` takes a `User` rather than
 * an optional actor so that there is no spelling of the call that means "the
 * system did it". The same argument as 2408's *"nothing ever writes `false`
 * automatically"* on the platform halt.
 *
 * ⛔ **THIS IS NOT THE SEND PERMIT.** Consent, suppression, STOP, the Do Not
 * Call registers and the quiet-hour windows are downstream and unchanged — see
 * {@see ThreadState}'s own docblock. Nothing here says a *person* may be
 * messaged.
 *
 * ⚠️ **NO CONVERSATION IS CREATED HERE, AND THAT IS STILL TRUE — BUT THE REST
 * OF THIS PARAGRAPH IS NOT.** It read *"today nothing in `app/` creates one"*,
 * which was correct when written and stopped being correct when P18 landed
 * `ConversationThreads::openFor()`, called from the carrier webhook. The
 * columns this class maintains are now exercised by real threads rather than
 * only by tests. ⚠️ **The sentence is corrected rather than deleted** because
 * 272's shape is what it was warning about and the warning still applies to the
 * next table; CLAUDE.md 2505 is the failure of leaving it as written.
 */
final class AgentThreadStates implements AgentThreads
{
    /**
     * ⛔ **A FLOOR, NOT A DEFAULT.** `assistant.turn_cap` is the figure; this is
     * the refusal to let an operator set it to a number that silences the
     * assistant on every thread at once, which looks identical to a model
     * outage on every screen. A registry key with no floor is a kill switch
     * nobody meant to build.
     */
    public const int MINIMUM_TURN_CAP = 1;

    /**
     * ⚠️ **THE CONTRACT, NOT `CampaignReplyResolver`** (4236). This class needs
     * one question answered — *which send did this thread begin as a reply to* —
     * and the concrete class also holds the write-side helper `tenantFor()` and
     * the whole candidate-matching machinery, none of which may run from a
     * rendering path. Depending on the interface is what keeps that true by
     * construction rather than by care.
     */
    public function __construct(
        private readonly DefaultsRegistry $registry,
        private readonly ActivityService $activity,
        private readonly AuditService $audit,
        private readonly CampaignContextResolver $campaigns,
    ) {}

    /**
     * ⚠️ **IT RE-READS THE ROW RATHER THAN TRUSTING THE MODEL IT WAS HANDED, AND
     * RAIL 4 IS WHY.** A queued job holding a `Conversation` hydrated before the
     * owner pressed send would otherwise read `AgentHandling` and speak — the
     * latch defeated by an object that is merely a few seconds old, which is the
     * commonest way a serialised job goes wrong. The contract's *"read it once
     * per turn and act on that value"* means once, **at the turn**, not once
     * whenever the model happened to be loaded.
     */
    public function stateFor(Conversation $conversation): ThreadState
    {
        $this->refuseForeignThread($conversation);

        return $this->stateOf($this->reread($conversation));
    }

    /**
     * Silence the agent on this thread because a person replied (rail 4).
     *
     * ⚠️ **IDEMPOTENT, AND THE FIRST TIMESTAMP IS THE ONE THAT STAYS.** The
     * owner sending three messages in a row latches once — the Inbox shows
     * `agent_latched_at` as *"silenced since"*, and moving it on every message
     * would make a thread somebody took over an hour ago read as brand new.
     *
     * ⛔ **AND IT NEVER MOVES A CLOSED THREAD.** A close summary has already
     * gone to the owner (P13, rail 9); latching a closed thread would leave the
     * account claiming a person is answering a conversation that finished.
     * Replying on a closed thread is a new thread, which is `isReArmable()`'s
     * own reasoning at the other end.
     */
    public function latch(Conversation $conversation, User $actor): ThreadState
    {
        $this->refuseForeignThread($conversation);

        [$state, $changed] = $this->mutate($conversation, function (Conversation $locked) use ($actor): bool {
            if ($locked->agent_status === AgentThreadStatus::Closed) {
                return false;
            }

            if ($locked->agent_status === AgentThreadStatus::HumanTakeover) {
                // The second and third message of a burst. Nothing moves —
                // deliberately including `agent_latched_by`, because the Inbox
                // names who is answering and the person who took it over is the
                // answer, not whoever typed most recently.
                return false;
            }

            $locked->forceFill([
                'agent_status' => AgentThreadStatus::HumanTakeover,
                'agent_latched_at' => Carbon::now(),
                'agent_latched_by' => (int) $actor->id,
            ])->save();

            return true;
        });

        if ($changed) {
            $this->record(
                $conversation,
                AutopilotActionType::AssistantHandedOver,
                'agent.thread.latched',
                'user:'.$actor->id,
            );
        }

        return $state;
    }

    /**
     * Hand the thread back to the agent — a person's act, always.
     *
     * ⛔ **RE-ARMING RESETS THE TURN COUNT, AND THAT IS A DECISION RATHER THAN
     * A TIDY-UP.** Rail 3 caps a *stretch* of agent turns; a thread that capped
     * at twelve and was then handed back would, without the reset, cap again on
     * the next turn — so the re-arm control would be a button that does nothing
     * on precisely the threads it exists for, with every screen reading
     * correctly (272's shape wearing a control). The person pressing it has read
     * the conversation and decided the assistant may continue, which is the same
     * judgement that starts a thread.
     *
     * ⚠️ **THE CAP ITSELF IS RE-READ FROM THE REGISTRY HERE**, and this is the
     * one place it moves. A stretch is judged by the cap it started under, so a
     * new stretch takes today's figure — which is what makes moving
     * `assistant.turn_cap` reach threads that are already open.
     *
     * ⚠️ **A THREAD THAT CANNOT BE RE-ARMED IS A NO-OP, NOT AN EXCEPTION.**
     * `Closed` is the case that matters and the Inbox's re-arm control is
     * hidden for it, so a call arriving here is a stale screen or a double
     * submit rather than a caller error — and throwing would turn a second click
     * into a 500 on a compliance-shaped path.
     */
    public function rearm(Conversation $conversation, User $actor): ThreadState
    {
        $this->refuseForeignThread($conversation);

        [$state, $changed] = $this->mutate($conversation, function (Conversation $locked): bool {
            if (! $locked->agent_status->isReArmable()) {
                return false;
            }

            $locked->forceFill([
                'agent_status' => AgentThreadStatus::AgentHandling,
                'agent_turns_used' => 0,
                'agent_turn_cap' => $this->configuredTurnCap(),
                'agent_latched_at' => null,
                'agent_latched_by' => null,
            ])->save();

            return true;
        });

        if ($changed) {
            $this->record(
                $conversation,
                AutopilotActionType::AssistantHandedBack,
                'agent.thread.rearmed',
                'user:'.$actor->id,
            );
        }

        return $state;
    }

    /**
     * Count one agent turn against rail 3's cap.
     *
     * ⚠️ **CALLED WHEN THE TURN IS TAKEN, NOT WHEN IT SUCCEEDS** — the
     * contract's own rule. A model reply that failed to send still consumed a
     * turn's worth of loop, and counting only successes is how a failing thread
     * runs for ever.
     *
     * ⛔ **A TURN IS NOT COUNTED ON A THREAD THE AGENT MAY NOT SPEAK ON.** A
     * latched thread that somehow reached here must not have its counter moved:
     * the count is the evidence for the cap, and a latched thread accruing turns
     * would cap silently while a person was answering, so re-arming it would
     * hand back a thread with no turns left.
     *
     * ⚠️ **THE CAP IS CAPTURED ON THE FIRST TURN AND NOT BEFORE.** A thread with
     * a NULL cap has not started a stretch; the first turn is what starts one,
     * and that is the moment today's registry figure becomes this thread's.
     */
    public function recordTurn(Conversation $conversation): ThreadState
    {
        $this->refuseForeignThread($conversation);

        [$state, $capped] = $this->mutate($conversation, function (Conversation $locked): bool {
            $before = $this->stateOf($locked);

            if (! $before->mayTakeTurn()) {
                return false;
            }

            $used = $before->turnsUsed + 1;
            $reachedCap = $used >= $before->turnCap;

            $locked->forceFill([
                'agent_turns_used' => $used,
                'agent_turn_cap' => $before->turnCap,
                'agent_status' => $reachedCap
                    ? AgentThreadStatus::TurnCapped
                    : AgentThreadStatus::AgentHandling,
            ])->save();

            return $reachedCap;
        });

        if ($capped) {
            // Rail 3: the cap hands the thread to the owner. The activity item
            // is the owner-visible half.
            $this->record(
                $conversation,
                AutopilotActionType::AssistantReachedItsTurnLimit,
                'agent.thread.turn_capped',
                'assistant',
            );

            // ⛔ **RAIL 9's THIRD CASE.** *"Every thread close (resolved,
            // escalated, or capped) → one-line outcome summary in the owner
            // notify."*
            //
            // ✅ **ALL THREE HAVE LIVE CALLERS SINCE 4540–4543, AND THIS COMMENT
            // CLAIMED THIS WAS "THE ONLY ONE WITH A LIVE CALLER TODAY" WHILE
            // CLAIMING `AnswerAgentTurnJob` "RUNS IN PRODUCTION" — WHICH NOTHING
            // DISPATCHED** (4534). Both halves were wrong in the same sentence.
            // The cap is now genuinely reached, `Inbox::finish()` closes, and
            // `AnswerAgentTurnJob::escalate()` and `AgentTurns` escalate.
            $this->summarise($conversation, AgentThreadStatus::TurnCapped);
        }

        return $state;
    }

    /**
     * Finish a thread — rail 9's *resolved*.
     *
     * ⛔ **CLOSING IS ONE-WAY**, which is `AgentThreadStatus::isReArmable()`'s
     * own reasoning at the other end: reopening a resolved conversation is a new
     * thread, so that the close summary already sent to the owner stays true.
     *
     * ⚠️ **IDEMPOTENT, AND A SECOND CLOSE SENDS NO SECOND SUMMARY.** A double
     * submit from the Inbox is the ordinary case, and the owner notify is the
     * part of this that a duplicate is most visible in.
     *
     * ✅ **IT HAS THE CALLER THIS DOCBLOCK PREDICTED** (4543). The paragraph read
     * *"nothing resolves a thread today — that is P18's control"*, and P18 then
     * shipped the screen **without** it, so `close()` sat uncalled through two
     * further lanes and rail 9's *resolved* summary was unreachable from
     * anywhere. `Inbox::finish()` is the control. ⚠️ **Naming the future caller
     * correctly is not the same as having one**, which is the lesson worth
     * keeping from the sentence this replaces.
     */
    public function close(Conversation $conversation, ?User $actor = null): ThreadState
    {
        $this->refuseForeignThread($conversation);

        [$state, $changed] = $this->mutate($conversation, function (Conversation $locked): bool {
            if ($locked->agent_status === AgentThreadStatus::Closed) {
                return false;
            }

            $locked->forceFill(['agent_status' => AgentThreadStatus::Closed])->save();

            return true;
        });

        if ($changed) {
            $this->record(
                $conversation,
                AutopilotActionType::AssistantFinishedAConversation,
                'agent.thread.closed_by_actor',
                $actor instanceof User ? 'user:'.$actor->id : 'assistant',
            );

            $this->summarise($conversation, AgentThreadStatus::Closed);
        }

        return $state;
    }

    /**
     * Hand a thread to the owner because the assistant cannot answer it — rail
     * 9's *escalated*, and rails 5 and 6's landing place.
     *
     * ⚠️ **DISTINCT FROM THE LATCH, AND THE DIFFERENCE IS WHO ACTED.** A latch
     * is a person starting to answer; this is the assistant stopping. They read
     * identically on a status column and need opposite sentences.
     *
     * ⛔ **AND FOR THREE MONTHS THIS METHOD FILED THE LATCH'S SENTENCE, THREE
     * LINES BELOW THE PARAGRAPH ABOVE — CORRECTED 2026-08-22 (7340).** The feed
     * row was `AutopilotActionType::AssistantHandedOver`, *"You took over a
     * conversation"*, defined in the enum as *"a person took a conversation over
     * from the assistant"* and annotated *"SECOND PERSON, BECAUSE THE OWNER'S
     * SIDE DID THIS"* — while the audit row from the **same call**, two
     * arguments later, wrote the actor as the literal `'assistant'`, and all
     * three callers are the assistant giving up. **The owner had done nothing and their own history
     * told them they had.** It is now
     * {@see AutopilotActionType::AssistantAskedYouToTakeOver}, whose
     * `needsOwner()` is true — the second half the wrong sentence cost, because
     * `AssistantHandedOver` is correctly `false` and an escalated thread
     * therefore reached the feed with no *"Needs you"* pill while a member of
     * the public waited.
     *
     * ⛔ **NOTHING MECHANICAL FOUND IT AND NOTHING MECHANICAL COULD.** This call
     * site is structurally perfect — it overrides no title, it is gated on
     * `$changed` from a row-locked mutate, it fires only on the arm that wrote —
     * and it was green against every lint in `ActivityTest.php`, because the
     * only thing wrong was the sentence and the right one did not exist. What
     * replaces the absent detector is the method-level enumeration in that file
     * (7345), which puts *"`escalate()` files this sentence"* in front of a
     * reviewer rather than trying to judge it.
     *
     * ⛔ **AND UNLIKE A CLOSE IT IS RE-ARMABLE**, because the thread is still
     * live: the owner may answer it and hand it back.
     *
     * ✅ **IT HAS TWO CALLERS, AND ONE OF THEM IS THE ONE THIS DOCBLOCK NAMED**
     * (4541, 4534). It read *"the composer's needs-owner draft and the
     * urgent-terms path are where this belongs and neither writes state today"* —
     * and the needs-owner draft is now `AnswerAgentTurnJob::escalate()`, which
     * fires only from `AgentHandling` so that a capped thread keeps its own
     * status and its own summary. The second caller is `AgentTurns`, refusing a
     * covered entity's turn (4534), and it is the one that made this reachable at
     * all.
     *
     * ✅ **AND THE THIRD IS THE URGENT-TERMS PATH THIS DOCBLOCK PREDICTED,
     * WHICH IT THEN CARRIED AS OWED FOR THREE LANES — CLOSED 2026-08-18** (5400,
     * closing 5323 and 5334(b)). The sentence here read ⚠️ *"the urgent-terms
     * path is still not a caller … nothing turns an urgent term into a state
     * change or an owner SMS"*, and it was true: `UrgentTerms::matches()` had
     * **zero callers in `app/`**, so skill 9 lit in `AgentSkills`, reached the
     * prompt as text, and the model decided what urgent meant.
     * `AgentTurns::escalateAsUrgent()` is the caller, and it escalates
     * **synchronously on the inbound path** — before any dispatch, so no vendor
     * outage, exhausted AI balance or queue backlog can silence it.
     *
     * ⚠️ **THE "OWNER SMS" HALF IS NOT CLOSED AND WAS NEVER THIS CLASS'S.**
     * There is no unpermitted `send()` on the SMS side and its absence is argued
     * at length in `PlatformTexter`. §2.2 row 9's *"owner SMS+email ping"* ships
     * as email only, and texting an account holder on a system event needs a
     * recorded basis and therefore a ruling.
     *
     * ⚠️ **A SECOND ESCALATION SUMMARY IS WHAT THE URGENT PATH COSTS.** Skill 9
     * mails its own page (`UrgentMessageEscalated`) *and* this method's rail-9
     * close summary goes as usual, because rail 9 applies to every escalation.
     * Two mails for one event, argued in that notification's docblock and
     * recorded as owed at 5414 — merging them needs a reason threaded through
     * `SummariseClosedThreadJob` and `ThreadCloseSummaries`, which is three
     * shared classes and not this slice's.
     * ⚠️ **THE SECOND MAIL NO LONGER CONTRADICTS THE FIRST — 7461.** Rail 9's
     * notify went out on every escalation with the subject *"A conversation
     * finished"* and the greeting *"One for your records"*, about a thread this
     * method has just handed to the owner and which is live and unanswered. It
     * now reads *"A conversation needs you"*. **That is a correction and not a
     * merge**: 5414 stays open, and the noise is unchanged.
     */
    public function escalate(Conversation $conversation): ThreadState
    {
        $this->refuseForeignThread($conversation);

        [$state, $changed] = $this->mutate($conversation, function (Conversation $locked): bool {
            if (in_array($locked->agent_status, [AgentThreadStatus::Escalated, AgentThreadStatus::Closed], true)) {
                return false;
            }

            $locked->forceFill(['agent_status' => AgentThreadStatus::Escalated])->save();

            return true;
        });

        if ($changed) {
            $this->record(
                $conversation,
                AutopilotActionType::AssistantAskedYouToTakeOver,
                'agent.thread.escalated',
                'assistant',
            );

            $this->summarise($conversation, AgentThreadStatus::Escalated);
        }

        return $state;
    }

    /**
     * Rail 9's notify, off the request — P13.
     *
     * ⛔ **`afterCommit()`, AND IT IS LOAD-BEARING RATHER THAN TIDY.** Every
     * caller here is inside {@see self::mutate()}'s transaction, so a job
     * dispatched immediately can be picked up by a worker before the status is
     * committed — and it would read the thread as still open and summarise a
     * conversation that had not ended. `AgentThreadStates` is also the class
     * whose whole design is that a reader never sees a stale value.
     */
    private function summarise(Conversation $conversation, AgentThreadStatus $status): void
    {
        $locationId = $conversation->location_id;

        SummariseClosedThreadJob::dispatch(
            Tenancy::idOrFail(),
            is_numeric($locationId) ? (int) $locationId : null,
            (int) $conversation->getKey(),
            $status,
        )->afterCommit();
    }

    /**
     * Run a write under the row lock and return the state that resulted.
     *
     * ⚠️ **THE STATE IS READ INSIDE THE TRANSACTION, AFTER THE WRITE.** Reading
     * it outside would hand the caller a value that another transaction may
     * already have moved — the same staleness the lock exists to remove, put
     * back one line later.
     *
     * @param  callable(Conversation): bool  $write  Answers whether anything
     *                                               changed, which is what
     *                                               decides whether the feed and
     *                                               the audit log are written.
     * @return array{ThreadState, bool}
     */
    private function mutate(Conversation $conversation, callable $write): array
    {
        /** @var array{ThreadState, bool} $result */
        $result = DB::transaction(function () use ($conversation, $write): array {
            $locked = Conversation::query()
                ->lockForUpdate()
                ->find($conversation->getKey());

            if (! $locked instanceof Conversation) {
                // The global scope and RLS both answer "not this tenant's" the
                // same way a deleted row does. Refusing rather than creating is
                // the whole point: this class maintains state on a thread, it
                // does not decide that one exists.
                throw new RuntimeException(
                    'This conversation is not available to the tenant in context, so its agent state cannot be changed.',
                );
            }

            $changed = $write($locked);

            $state = $this->stateOf($locked);

            // Keep the caller's instance in step, so a screen that holds the
            // model it passed in does not render the value it had before.
            $conversation->setRawAttributes($locked->getAttributes(), true);

            return [$state, $changed];
        });

        return $result;
    }

    /**
     * The row as it stands now, refusing rather than inventing one.
     *
     * ⚠️ **THE GLOBAL SCOPE AND RLS BOTH ANSWER "NOT THIS TENANT'S" THE WAY A
     * DELETED ROW DOES**, which is why the miss is a refusal and not a `fresh()`
     * that quietly returns null. This class maintains state on a thread; it does
     * not decide that one exists.
     */
    private function reread(Conversation $conversation): Conversation
    {
        $found = Conversation::query()->find($conversation->getKey());

        if (! $found instanceof Conversation) {
            throw new RuntimeException(
                'This conversation is not available to the tenant in context, so its agent state cannot be read.',
            );
        }

        return $found;
    }

    /**
     * Read the state off a row without touching the database again.
     */
    private function stateOf(Conversation $conversation): ThreadState
    {
        $latchedAt = $conversation->agent_latched_at;

        return new ThreadState(
            status: $conversation->agent_status,
            turnsUsed: max(0, $conversation->agent_turns_used),
            turnCap: $this->turnCapOf($conversation),
            latchedAt: $latchedAt instanceof Carbon ? $latchedAt : null,
            // ✅ **CAMPAIGN CONTEXT IS READ THROUGH P20's OWN CONTRACT, AND
            // STILL NOT INVENTED HERE** (4236). This line was a hardcoded
            // `null` — correct while nothing could answer the question, and a
            // false render the moment something could: `isCampaignReply()` was
            // `false` in production always, so R20's context reached no screen
            // while `campaign_replies` filled up. `null` remains the common and
            // correct answer for a missed-call thread; what changed is that it
            // is now the resolver's answer rather than this class's assumption.
            //
            // ⚠️ **ONE INDEXED ROW READ, ON EVERY STATE READ INCLUDING THE ONES
            // UNDER THE ROW LOCK.** Filling it only in `stateFor()` would make
            // `latch()`, `rearm()` and `recordTurn()` each hand back a state
            // claiming no campaign — the same false render in three more
            // places, and the ones a caller is most likely to act on.
            campaign: $this->campaigns->forThread($conversation),
        );
    }

    /**
     * The cap this thread is judged by.
     *
     * The stored figure when the thread has started a stretch, today's registry
     * figure when it has not.
     */
    private function turnCapOf(Conversation $conversation): int
    {
        $stored = $conversation->agent_turn_cap;

        if (is_int($stored) && $stored >= self::MINIMUM_TURN_CAP) {
            return $stored;
        }

        return $this->configuredTurnCap();
    }

    private function configuredTurnCap(): int
    {
        return max(self::MINIMUM_TURN_CAP, $this->registry->int('assistant.turn_cap'));
    }

    /**
     * Refuse a thread belonging to another tenant, loudly and before any write.
     *
     * ⚠️ **THIS IS THE APPLICATION LAYER'S CONTRIBUTION AND NOT THE ONLY ONE.**
     * The global scope on {@see Conversation} and the RLS policy beneath it both
     * hide the row; this exists because a caller can hand in a *hydrated* model
     * loaded under a different tenant, which no scope and no policy can see. RLS
     * catches a forgotten filter, never a wrong one.
     */
    private function refuseForeignThread(Conversation $conversation): void
    {
        $businessId = Tenancy::idOrFail();

        if (! $conversation->exists) {
            // An unsaved model — the day-0 test's probe, and the shape a caller
            // reaches for when it means "a new thread". There is nothing to
            // scope and nothing to write.
            throw new RuntimeException(
                'A conversation must be saved before the assistant can hold state on it.',
            );
        }

        if ((int) $conversation->business_id !== $businessId) {
            throw new RuntimeException(
                'This conversation belongs to another tenant, so its agent state cannot be read or changed.',
            );
        }
    }

    /**
     * One event, in both books.
     *
     * ⚠️ **TWO WRITES BECAUSE THEY HAVE TWO AUDIENCES**, which is
     * `ActivityService`'s own note: the feed is what the owner reads, in their
     * language; the audit log is what compliance reads, in full detail, and rail
     * 4 is a claim about who was speaking to a customer that has to be
     * answerable years later.
     *
     * ⛔ **NO MESSAGE TEXT IN EITHER, EVER.** The conversation id and the actor
     * answer every question these records exist for; the customer's own words
     * live in the thread, under the consent gate that admitted them, and copying
     * them into two append-only stores would put them somewhere an erasure
     * request cannot reach cleanly.
     */
    private function record(
        Conversation $conversation,
        AutopilotActionType $action,
        string $auditAction,
        string $actor,
    ): void {
        $locationId = $conversation->location_id;

        $this->activity->record(
            action: $action,
            locationId: is_numeric($locationId) ? (int) $locationId : null,
            metadata: ['conversation_id' => (int) $conversation->getKey()],
        );

        $this->audit->record(
            action: $auditAction,
            actor: $actor,
            entity: $conversation,
            metadata: [
                'agent_status' => $conversation->agent_status->value,
                'agent_turns_used' => $conversation->agent_turns_used,
            ],
        );
    }
}
