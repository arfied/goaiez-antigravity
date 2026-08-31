<?php

declare(strict_types=1);

namespace App\Enums;

use App\Jobs\AutopilotJob;

/**
 * The lifecycle of one automation run (DATA-MODEL §5.12).
 *
 * `Skipped` is not a failure and must never be reported as one. It is the
 * outcome when a toggle is off, a kill switch is thrown, or the work was already
 * done — all of which are the system behaving correctly, and all of which the
 * owner should be able to see without alarm.
 */
enum AutomationRunStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /** Deliberately not attempted. A toggle, a kill switch, or already done. */
    case Skipped = 'skipped';

    /** Ran the no-API path because the API was unavailable or unauthorised. */
    case HandedOff = 'handed_off';

    /**
     * The process running it was killed and never came back.
     *
     * ⛔ **THE ONE FACT `Failed` COULD NOT HOLD, AND
     * {@see AutopilotJob::failed()}'s OWN DOCBLOCK SAYS SO** — *"that
     * row is an attempt and this is a surrender, and nothing in the schema tells
     * them apart"*. `handle()`'s `catch` writes `Failed` and **rethrows so the
     * queue retries**, so a `Failed` row is usually a step on the way to a
     * success. This is the other thing: a worker outran `--timeout`, the process
     * exited from inside a signal handler, and `handle()`'s `catch` and `finally`
     * never ran. Nobody is coming back to it.
     *
     * ⛔ **AND THE PART THAT IS NOT ABOUT REPORTING: THE IDEMPOTENCY CLAIM IS
     * PROBABLY STILL HELD.** The release lives in a `finally` a killed process
     * skips, so the row keeps its `idempotency_key` and every later dispatch for
     * that subject collides in `claimRun()` and returns before `execute()`.
     * `output.claim_held` on the row is the answer for that row, and 9962 is why
     * the base class hands nothing back on its own.
     *
     * ⚠️ **WHETHER THE WORK HAPPENED IS UNKNOWABLE AND THIS CASE MUST NOT BE
     * READ AS "IT DID NOT".** A job killed mid-`Http::post()` may have been
     * killed after the carrier accepted the message. That is the whole reason
     * `AutopilotJob::claimIsSpent()` defaults to keeping the claim, and this
     * status inherits the same uncertainty rather than resolving it.
     */
    case Abandoned = 'abandoned';

    public function isTerminal(): bool
    {
        return $this !== self::Running;
    }

    /**
     * Whether this outcome represents something going wrong.
     *
     * Skipped and HandedOff both did the right thing, so neither counts.
     *
     * ⚠️ **{@see self::Abandoned} COUNTS, AND IT IS THE ONE ARM WHERE THE
     * OUTCOME IS UNKNOWN RATHER THAN BAD.** A killed worker may have completed
     * the work before it died. It is counted anyway, because the alternative is
     * that the only outcome nobody can account for is also the only one nothing
     * reports — and this method's readers ask *"is there something to look
     * at?"*, to which the honest answer for an unaccountable run is yes.
     */
    public function isFailure(): bool
    {
        return $this === self::Failed || $this === self::Abandoned;
    }
}
