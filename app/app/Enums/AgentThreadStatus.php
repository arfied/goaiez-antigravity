<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether `missed_call.sms_agent` may speak on a thread, and why not when it
 * may not (T176 §2.3 rails 3, 4 and 6).
 *
 * ⚠️ **THE STATES ARE REASONS, NOT A PROGRESS BAR.** Four of the six are ways
 * the agent stops talking, and they are deliberately distinct: a thread the
 * owner took over, a thread that hit its turn cap, a thread escalated to a
 * person and a thread that closed are the same silence with four different
 * remedies. Collapsing them into one `inactive` would make the Inbox (P18)
 * unable to say why the assistant went quiet, which is the first question a
 * business owner asks.
 *
 * ⛔ **`HumanTakeover` IS A LATCH AND NOT A MODE.** Rail 4: *"the moment a human
 * replies on the thread the agent goes silent on that thread until explicitly
 * re-armed. No exceptions."* Nothing in the conversation returns it to
 * `AgentHandling` — only a person's re-arm does. A state machine that let an
 * inbound message move it back would defeat the rail on the most ordinary event
 * there is.
 */
enum AgentThreadStatus: string
{
    /**
     * No agent turn has been taken. The thread exists — a missed call landed, or
     * a campaign reply arrived — and nothing has answered yet.
     */
    case Unhandled = 'unhandled';

    /** The agent owns this thread and may take its next turn. */
    case AgentHandling = 'agent_handling';

    /**
     * A person replied, so the agent is latched silent until re-armed (rail 4).
     */
    case HumanTakeover = 'human_takeover';

    /**
     * Handed to the owner — an urgent term, a refusal the agent cannot answer,
     * or a model outage (rails 5 and 6). The owner was notified.
     */
    case Escalated = 'escalated';

    /**
     * The turn cap fired (rail 3, seeded at 12). Distinct from `Escalated`
     * because the owner notification says something different and because a cap
     * is the agent working correctly rather than a failure.
     */
    case TurnCapped = 'turn_capped';

    /** Resolved. A close summary was written to the owner notify (P13). */
    case Closed = 'closed';

    /**
     * Whether the agent may take a turn on a thread in this state.
     *
     * ⚠️ **THE ONLY PLACE THIS QUESTION IS ANSWERED.** A caller comparing states
     * by hand is how one of the four silences gets forgotten — the outbound path
     * asks this and nothing else.
     */
    public function agentMaySpeak(): bool
    {
        return match ($this) {
            self::Unhandled, self::AgentHandling => true,
            self::HumanTakeover, self::Escalated, self::TurnCapped, self::Closed => false,
        };
    }

    /**
     * Whether a person can hand this thread back to the agent.
     *
     * A latched thread re-arms; a closed one does not — reopening a resolved
     * conversation is a new thread, so that the close summary already sent to
     * the owner stays true.
     */
    public function isReArmable(): bool
    {
        return match ($this) {
            self::HumanTakeover, self::Escalated, self::TurnCapped => true,
            self::Unhandled, self::AgentHandling, self::Closed => false,
        };
    }

    /**
     * What the Inbox tells the owner, in outcome language (`22`).
     */
    public function ownerLabel(): string
    {
        return match ($this) {
            self::Unhandled => 'Waiting for a first reply',
            self::AgentHandling => 'Your assistant is answering',
            self::HumanTakeover => 'You are answering',
            self::Escalated => 'Needs you',
            self::TurnCapped => 'Handed to you after a long conversation',
            self::Closed => 'Finished',
        };
    }
}
