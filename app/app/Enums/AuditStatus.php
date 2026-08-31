<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The lifecycle of one free instant audit (`29` §6.2).
 *
 * Deliberately narrower than AutomationRunStatus, which carries `Skipped` and
 * `HandedOff` because an automation runs inside a tenant with toggles, kill
 * switches and a no-API fallback path. A public audit has none of those — it is
 * not an AutopilotJob and must not become one (decision 198) — so importing
 * that vocabulary would create states nothing can ever produce.
 *
 * `Failed` is a state the public page renders honestly rather than hides.
 * BUILD-PLAN §2.5.3 requires that a check which cannot run says so instead of
 * fabricating a result; a whole audit that cannot run is the same rule one
 * level up — no score, a plain sentence, and a way to try again.
 */
enum AuditStatus: string
{
    /** Row written, job queued, nothing measured yet. */
    case Queued = 'queued';

    /** Checks are executing; findings stream in as each completes. */
    case Running = 'running';

    /** Every check that could run has run. The score is set. */
    case Complete = 'complete';

    /**
     * The audit could not produce a result — the place did not resolve, or the
     * daily budget was exhausted before it started (decision 193, fail closed).
     */
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::Complete || $this === self::Failed;
    }

    /**
     * Whether the client should keep polling `GET /api/public/audit/{token}`.
     */
    public function isPending(): bool
    {
        return ! $this->isTerminal();
    }
}
