<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\PollSupportMailboxJob;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * "Go and look in the support mailbox" — T176 §3's pull, on a clock.
 *
 * ⚠️ **IT DISPATCHES AND DOES NOTHING ELSE**, which is `RecordOpsHeartbeat`'s
 * shape and is here for the reason `IngestGmailPushJob` gives: the Gmail reads
 * are network calls against an endpoint that rate-limits, and a slow mailbox
 * must not hold the scheduler's tick. The job is where a failure is a failed job
 * somebody can read.
 *
 * ⛔ **THE LAST SENTENCE IS FALSE, AND ON A SCHEDULED COMMAND IT IS FALSE TWICE
 * OVER — CORRECTED 2026-08-25 (9591, 9593).** **Nobody reads `failed_jobs`**
 * (9370). ⛔ **AND THE INSTRUMENT THAT DOES WATCH THIS ENTRY CANNOT SEE THE JOB
 * AT ALL**: `handle()` dispatches and returns `SUCCESS`, so
 * `ScheduledRunMeter::finished()` records a completed run with a duration, and
 * its `failed()` fires only when `Event::run()` itself throws. **This command
 * cannot fail in the state it exists to perform.** So a job failing permanently
 * every five minutes for ever produces an unbroken record of successful
 * scheduled runs — which is 9371's `emailed_at` one layer up: *a signal that
 * records a hand-over, read as though it recorded an outcome.*
 *
 * ✅ **WHAT IS TRUE INSTEAD, AND IT IS WHY NO BELL IS ADDED HERE.** Nothing is
 * lost. {@see PollSupportMailboxJob} moves the bookmark only when a whole range
 * has been read (9488–9490), and it deliberately takes **no per-message
 * `Cache::add()` claim** — its own docblock argues that, and it is what keeps
 * the job free of the stranding defect the push path carried until 9585. So a
 * permanent failure here **delays rather than deletes**, bounded by Gmail's own
 * history retention, after which the gap is announced. ⚠️ **A support request
 * arriving late is still the thing a person is waiting for**, and what would arm
 * a bell over it is written down at 9494 rather than guessed at here.
 *
 * ⚠️ **NO KILL-SWITCH CHECK HERE.** {@see PollSupportMailboxJob} re-reads it on
 * the queue, and asking twice would let a `--dry-run`-shaped habit grow around
 * the half that is not the guarantee.
 */
#[AsCommand(name: 'support:poll-mailbox')]
final class PollSupportMailbox extends Command
{
    protected $description = 'Read the goaiez support mailbox and put what arrived on the support desk';

    public function handle(): int
    {
        PollSupportMailboxJob::dispatch();

        return self::SUCCESS;
    }
}
