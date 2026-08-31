<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a support session stopped (`28` §9.4's "auto-end on TTL, tab close, or
 * agent logout", plus the one it does not name).
 *
 * A `string` column cast to this enum, never a database enum.
 *
 * The distinction earns its column because these read differently in a dispute.
 * `Ended` is an agent finishing; `Expired` is the clock doing its job; `Revoked`
 * is somebody else stopping them, which is the only one of the four that is
 * ever a story. A single `ended_at` timestamp would answer "when" and leave
 * "why" to be inferred from the timing, which is exactly the kind of inference
 * an audit is supposed to remove.
 */
enum ImpersonationEnding: string
{
    /** The agent pressed End session. */
    case Ended = 'ended';

    /** The TTL lapsed. Recorded the first time anything looks at the session afterwards. */
    case Expired = 'expired';

    /** The agent logged out, or their own session went away underneath them. */
    case AgentLogout = 'agent_logout';

    /**
     * Somebody else stopped it — `28` §9.4's "End all impersonations" red button.
     *
     * The button does not exist yet; the case does, because the field it writes
     * has to be able to hold the answer before the incident that needs it.
     */
    case Revoked = 'revoked';
}
