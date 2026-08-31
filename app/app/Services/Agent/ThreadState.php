<?php

declare(strict_types=1);

namespace App\Services\Agent;

use App\Enums\AgentThreadStatus;
use App\Services\Campaigns\CampaignContext;
use Illuminate\Support\Carbon;

/**
 * Everything the agent needs to know before it takes a turn (T176 §2.3).
 *
 * A day-0 contract: the Inbox (P18, L7) renders it, the campaign lane (P20, L3)
 * supplies its campaign half, and the agent core (P3, L5) is what fills it in.
 *
 * ⚠️ **ONE OBJECT, ASKED ONCE, BECAUSE THE RAILS ARE AND-ED AND EACH IS A
 * SEPARATE REFUSAL.** A turn is permitted only when the status allows it *and*
 * the cap has room. Two callers asking two questions is how one of them gets
 * asked in only one of the two places — 398's shape, where an outer guard
 * refuses first and the inner one is never exercised. {@see mayTakeTurn()} is
 * the whole gate.
 *
 * ⛔ **IT IS NOT THE SEND PERMIT.** Consent, suppression, STOP, the Do Not Call
 * registers and the quiet-hour windows are all downstream and unchanged: this
 * object says the *assistant* may speak, never that this *person* may be
 * messaged. Rail 10's spend check is downstream too.
 */
final readonly class ThreadState
{
    /**
     * @param  AgentThreadStatus  $status  Whether the agent owns the thread, and
     *                                     if not, which of the four silences it
     *                                     is in.
     * @param  int  $turnsUsed  Agent turns taken on this thread so far. A human's
     *                          replies are not turns — the cap exists to stop a
     *                          model looping, and counting the owner's messages
     *                          against it would cut off the busiest threads
     *                          first.
     * @param  int  $turnCap  Rail 3's cap, registry-keyed, seeded at 12. Carried
     *                        on the state rather than read at the call site so
     *                        that a thread already in flight is judged by the
     *                        cap it started under.
     * @param  ?Carbon  $latchedAt  When a person took the thread over (rail 4).
     *                              Non-null implies {@see AgentThreadStatus::HumanTakeover};
     *                              it is kept as a timestamp because the Inbox
     *                              shows it and re-arming needs something to
     *                              show as "silenced since".
     * @param  ?CampaignContext  $campaign  Which send this thread is answering,
     *                                      when it began as a campaign reply
     *                                      (R20). `null` for a missed-call
     *                                      thread, which is the common case.
     *                                      ⚠️ **AND `null` IS TWO-VALUED, SO NO
     *                                      SURFACE MAY RENDER IT AS "NOT A
     *                                      CAMPAIGN REPLY"** (4240): an absent
     *                                      linkage cannot be told from a
     *                                      linkage recorded without a thread.
     *                                      ⚠️ **THE ANSWER IS THE SEND THE
     *                                      THREAD *BEGAN* AS A REPLY TO** — a
     *                                      thread that stays open can draw a
     *                                      second linkage, and this reports the
     *                                      first (4238).
     */
    public function __construct(
        public AgentThreadStatus $status,
        public int $turnsUsed,
        public int $turnCap,
        public ?Carbon $latchedAt = null,
        public ?CampaignContext $campaign = null,
    ) {}

    /**
     * The state of a thread nothing has answered yet.
     */
    public static function fresh(int $turnCap, ?CampaignContext $campaign = null): self
    {
        return new self(AgentThreadStatus::Unhandled, 0, $turnCap, null, $campaign);
    }

    /**
     * Whether the agent may take its next turn — the only question the outbound
     * path asks.
     */
    public function mayTakeTurn(): bool
    {
        return $this->status->agentMaySpeak() && $this->hasTurnsLeft();
    }

    /**
     * ⚠️ **`>=`, NOT `>`.** The cap is the number of turns allowed, so a thread
     * that has used all twelve has none left. Writing this the other way gives
     * thirteen and is the kind of off-by-one that never fails a test written by
     * the same person.
     */
    public function hasTurnsLeft(): bool
    {
        return $this->turnsUsed < $this->turnCap;
    }

    public function turnsRemaining(): int
    {
        return max(0, $this->turnCap - $this->turnsUsed);
    }

    /**
     * Whether this thread began as a reply to a campaign send (R20).
     */
    public function isCampaignReply(): bool
    {
        return $this->campaign instanceof CampaignContext;
    }
}
