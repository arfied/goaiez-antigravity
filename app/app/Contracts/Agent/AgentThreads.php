<?php

declare(strict_types=1);

namespace App\Contracts\Agent;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Agent\ThreadState;

/**
 * The agent's view of a thread, and the acts that change it (T176 §2.3 rails 3,
 * 4 and 9).
 *
 * A day-0 contract. P18's Inbox calls {@see latch()} when the owner sends a
 * reply and {@see rearm()} from the thread control; P3 owns the implementation
 * and {@see recordTurn()}.
 *
 * ✅ **{@see close()} AND {@see escalate()} JOINED IT AT 4544, AND THEY WERE ON
 * THE IMPLEMENTATION ALL ALONG.** Both have existed on `AgentThreadStates` since
 * P3 with no caller in `app/` and no line here — so the two rail-9 outcomes the
 * turn cap does not produce were reachable only by naming the concrete class,
 * which is the exact evasion the paragraph below refuses. They are thread-state
 * transitions and they belong to whatever owns the five columns; widening the
 * contract is what makes `AgentTurns` and the Inbox able to reach them **through
 * the seam** rather than around it.
 *
 * ⚠️ **THE LATCH IS THE REASON THIS IS AN INTERFACE AND NOT A MODEL METHOD.**
 * Rail 4 admits no exceptions, and the thing most likely to break it is a second
 * place that sets the same column — a controller, a job, a Livewire component,
 * each with its own idea of what counts as "a human replied". One implementation
 * behind one contract is what makes *"the moment a human replies"* a single
 * line of code that can be driven red.
 *
 * ✅ **P3 HAS LANDED AND THE REFUSING IMPLEMENTATION IS GONE** (4239, following
 * `LinkRegistry`'s own correction at 4046). `UnbuiltAgentThreads` was deleted in
 * the commit that bound `App\Services\Agent\AgentThreadStates`, and the
 * `@throws UnbuiltPatch while P3 is unbuilt` this file carried on {@see rearm()}
 * until 2026-08-16 is removed rather than left to read as a live warning.
 * **A stale claim in a contract docblock is what stops the next reader
 * looking** — `CLAUDE.md` 2505's shape, in the one file every lane calling this
 * interface opens first. It was also the last mention of `UnbuiltPatch` left in
 * `app/`, so with it gone that type's subject population is genuinely empty and
 * `Architecture/ConventionsTest` now asserts as much rather than describing it.
 *
 * ⚠️ **AND `stateFor()` NOW ANSWERS THE CAMPAIGN HALF IT ALWAYS PROMISED**
 * (4236). *"Status, turns, latch, campaign context"* — the last of those was a
 * hardcoded `null` in the implementation, so `ThreadState::isCampaignReply()`
 * returned `false` in production always. The contract is unchanged; what changed
 * is that it is kept.
 */
interface AgentThreads
{
    /**
     * The current state of the thread — status, turns, latch, campaign context.
     *
     * ⚠️ **Read it once per turn and act on that value.** Re-reading between the
     * permission check and the send re-opens the race the latch exists to close:
     * the owner may have replied in between, which is precisely when the agent
     * must not speak.
     */
    public function stateFor(Conversation $conversation): ThreadState;

    /**
     * Silence the agent on this thread because a person replied (rail 4).
     *
     * ⚠️ **IDEMPOTENT, AND IT NEVER MOVES A CLOSED THREAD.** The owner sending
     * three messages in a row latches once, and the first timestamp is the one
     * that stays — it is what the Inbox shows as "silenced since".
     *
     * @param  User  $actor  Who took over. Recorded because "a human replied" is
     *                       an assertion the audit log has to be able to name
     *                       somebody for, and because the Inbox says which
     *                       teammate is answering.
     */
    public function latch(Conversation $conversation, User $actor): ThreadState;

    /**
     * Hand the thread back to the agent — a person's act, always (rail 4's
     * *"until explicitly re-armed"*).
     *
     * ⛔ **NOTHING AUTOMATIC MAY CALL THIS.** No inbound message, no timer, no
     * "the owner has not replied in an hour". A latch that expires on its own is
     * not a latch, and the failure it produces — the assistant resuming a
     * conversation a person had taken over — is the one R21 and rail 4 exist to
     * make impossible. The same argument as 2408's *"nothing ever writes `false`
     * automatically"* on the platform halt.
     */
    public function rearm(Conversation $conversation, User $actor): ThreadState;

    /**
     * Count one agent turn against rail 3's cap.
     *
     * ⚠️ **CALLED WHEN THE TURN IS TAKEN, NOT WHEN IT SUCCEEDS.** A model reply
     * that failed to send still consumed a turn's worth of loop; counting only
     * successes is how a failing thread runs forever.
     */
    public function recordTurn(Conversation $conversation): ThreadState;

    /**
     * Finish a thread — rail 9's *resolved* (4544).
     *
     * ⛔ **CLOSING IS ONE-WAY.** Reopening a resolved conversation is a new
     * thread, so that the close summary already sent to the owner stays true —
     * which is why `AgentThreadStatus::isReArmable()` answers false for it and
     * the Inbox hides the re-arm control.
     *
     * ⚠️ **IDEMPOTENT, AND A SECOND CLOSE SENDS NO SECOND SUMMARY.** A double
     * submit from the Inbox is the ordinary case, and the owner notify is the
     * part a duplicate is most visible in.
     *
     * @param  ?User  $actor  Who finished it, or null when nobody did. ⚠️
     *                        **NULLABLE BECAUSE A THREAD CAN END WITHOUT A
     *                        PERSON** — skill 15's wrong-number close is the
     *                        assistant's own act — and the audit row names
     *                        `assistant` rather than inventing a user for it.
     */
    public function close(Conversation $conversation, ?User $actor = null): ThreadState;

    /**
     * Hand a thread to the owner because the assistant cannot answer it — rail
     * 9's *escalated*, and rails 5 and 6's landing place (4544).
     *
     * ⚠️ **DISTINCT FROM THE LATCH, AND THE DIFFERENCE IS WHO ACTED.** A latch is
     * a person starting to answer; this is the assistant stopping. They read
     * identically on a status column and need opposite sentences.
     *
     * ⛔ **AND UNLIKE A CLOSE IT IS RE-ARMABLE**, because the thread is still
     * live: the owner may answer it and hand it back. ⚠️ **Re-arming does not
     * defeat whatever caused the escalation** — a covered entity's next inbound
     * message is refused again (4534) — so the control cannot become a way round
     * a rule.
     *
     * ⚠️ **NO ACTOR, DELIBERATELY.** There is no spelling of this call that means
     * "a person escalated it"; every caller is the assistant declining, and an
     * optional actor here would invite one that recorded a human decision nobody
     * made — `rearm()`'s argument with its sign reversed.
     */
    public function escalate(Conversation $conversation): ThreadState;
}
