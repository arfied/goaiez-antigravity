<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OperatorAlertKind;
use App\Enums\TenantDeletionOutcome;
use App\Models\TenantDeletionRequest;
use App\Services\Ops\OperatorAlerts;
use App\Services\Support\DataRequests;
use App\Services\Tenant\TenantDeletion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Destroy the accounts whose cooling window has expired.
 *
 * `28` §9.5's Delete, executed. The window is the feature; this is what happens
 * when nobody used it.
 *
 * ## ⚠️ THE ONLY THING THAT DESTROYS AN ACCOUNT, AND IT IS ON A CLOCK
 *
 * No screen deletes anything now. Support files a request, a second admin
 * confirms, and seven days later this command runs — so there is no button
 * anywhere whose consequence is immediate and irreversible, and every deletion
 * has a window in which an ordinary mistake is an ordinary cancellation.
 *
 * ⚠️ **IT RUNS WITH NO TENANT ESTABLISHED**, which is the constraint that shapes
 * {@see TenantDeletion::execute()}. `businesses` is RLS-`ENABLE`+`FORCE`d on
 * `app.business_id`, so nothing here can read a business until the service sets
 * the tenant from `business_ref` — and the version that read it through a scoped
 * relation found null every time, marked each request executed and deleted
 * nothing. Decision 569's wall, and the reason this command deliberately does no
 * querying of its own beyond {@see TenantDeletion::due()}.
 *
 * ## ⚠️ A REFUSAL IS NOT A FAILURE
 *
 * A request that cannot execute today is **left where it is** and reported. It
 * is not failed, not retried, and not closed. Decision 823's rule: failing
 * burns a ladder against a condition only a human clears, and 364's sweep
 * counts attempts.
 *
 * ⚠️ **AND THERE IS MORE THAN ONE WAY TO BE REFUSED, WHICH THIS LINE USED TO
 * DENY** (1992). It printed a fixed sentence about a live Stripe subscription,
 * because when it was written that was the only refusal {@see TenantDeletion}
 * had. 1902 added a second — the object store refusing to give up the tenant's
 * export ZIPs — and this message was not updated, so an operator watching
 * statutory deletions stop platform-wide would have been sent to Stripe, found
 * nothing wrong, and had no other thread to pull. The sentence now comes from
 * {@see TenantDeletionOutcome}, which owns one per refusal.
 *
 * ⚠️ **A NON-ZERO EXIT WOULD BE WRONG.** The scheduler discards output, so the
 * only signal an operator gets is an exit code, and "some accounts were not
 * deletable yet" is the steady state rather than an incident — every tenant with
 * a live subscription is in it today. A command that reddened on the normal case
 * is one whose red is ignored within a week.
 *
 * ## ⛔ ONE TENANT COULD STOP EVERY OTHER TENANT'S ERASURE, AND DID (8840, 8848)
 *
 * `handle()` contained no `try`, no `catch` and no `Throwable`. The loop below
 * calls two collaborators, and {@see TenantDeletion::execute()} raised a
 * foreign-key violation on any tenant holding one of `businesses`' three
 * `restrict` keys — so **the exception escaped the `foreach` and every account
 * queued behind the failing one was not erased that night**, and the same set
 * failed the same way the next night, for ever. Nothing rang. The blast radius
 * was the whole queue and the cause was one row.
 *
 * ⚠️ **THE `catch` IS PER REQUEST AND DELIBERATELY WIDE**, where the one in
 * `execute()` is deliberately narrow. That one turns a *database* refusal into
 * a named outcome, because a refusal is what it is. This one exists because
 * nothing a single request does may be allowed to reach the next one — and it
 * is the only thing standing between the queue and a collaborator this command
 * merely calls: the §9.5 queue update below is somebody else's method, and a
 * throw from it has exactly the same blast radius as a throw from the erasure.
 *
 * ⛔ **AND CONTAINING IT WOULD HAVE MADE IT QUIETER IF NOTHING RANG.** An
 * uncaught exception was at least logged by the framework's handler; a caught
 * one is logged by nobody unless somebody writes the line. So a failure raises
 * {@see OperatorAlertKind::TenantErasureFailed} — **once per run with a count**,
 * because the shape that matters most is the database being unreachable, where
 * a bell per account rings for every due account at once. **The exit code stays
 * 0**, for the reason above and R25's: a bell is never a brake.
 */
#[Signature('tenants:execute-deletions')]
#[Description('Destroy accounts whose deletion cooling window has expired')]
final class ExecuteTenantDeletions extends Command
{
    public function handle(
        TenantDeletion $deletions,
        DataRequests $dataRequests,
        OperatorAlerts $alerts,
    ): int {
        $due = $deletions->due();

        if ($due->isEmpty()) {
            $this->info('No deletions are due.');

            return self::SUCCESS;
        }

        $destroyed = 0;
        $deferred = 0;

        /** @var list<int> $failed */
        $failed = [];

        foreach ($due as $request) {
            /** @var TenantDeletionRequest $request */
            $reference = (int) $request->business_ref;

            try {
                $outcome = $deletions->execute($request);

                // ⚠️ **THE §9.5 QUEUE UPDATE IS INSIDE THE `try` AND IS ONE CALL
                // RATHER THAN TWO** (2045, 8848). It is somebody else's method
                // reached across a service boundary, and a throw from it stops
                // the night's queue exactly as thoroughly as a throw from the
                // erasure does. It runs on both closing paths for 2045's own
                // reason: `execute()` sets `executed_at` on the already-gone
                // path too, so the deletion row leaves `due()` and is never
                // printed again — while the §9.5 queue item that filed the
                // erasure stayed `AwaitingDeletion` for ever, on a screen, with
                // nothing left that would ever close it. **2045 fixed that by
                // duplicating the line, and a duplicate is exactly how one of
                // the two went missing in the first place.**
                if ($outcome->destroyed() || $outcome === TenantDeletionOutcome::AlreadyGone) {
                    $dataRequests->noteErasureExecuted($reference);
                }
            } catch (Throwable $e) {
                // ⛔ **THE WHOLE POINT OF THIS METHOD HAVING A `catch` AT ALL.**
                // Whatever went wrong belongs to this request and stops here;
                // the account is untouched, the row stays open, and the next
                // one in the queue gets its erasure tonight rather than in
                // however many nights it takes somebody to notice.
                //
                // ⚠️ **THE CLASS, NEVER THE MESSAGE.** A `QueryException`
                // interpolates its bindings, and the statements inside an
                // erasure carry a tenant's own phone number among them —
                // `TenantDeletion` makes the same argument at its own `catch`.
                $failed[] = $reference;

                Log::error('a tenant erasure failed and the sweep continued', [
                    'business_ref' => $reference,
                    'exception' => $e::class,
                ]);

                $this->warn(
                    "Account {$reference} is due and the erasure failed — "
                    .'nothing was destroyed and the request is still open. See the log.'
                );

                continue;
            }

            if ($outcome->destroyed()) {
                $destroyed++;

                // The reference, never a name: this line reaches a log file, and
                // `audit_log.actor`'s convention is that a label is safe where
                // personal data is not.
                $this->info("Deleted account {$reference}.");

                // ⛔ **A DESTROYED ACCOUNT CAN STILL OWE SOMETHING** (4882).
                // `destroyed()` is true for `DestroyedGrantOutstanding` — the
                // account really is gone and the §9.5 queue item above really is
                // closed — but a third party is still holding `business.manage`
                // on the former customer's Google listing. Printing only the
                // success line would report a clean erasure, which is the one
                // sentence an operator must not be told here.
                $outstanding = $outcome->outstandingMessage();

                if ($outstanding !== null) {
                    $this->warn("Account {$reference}: ".$outstanding);
                }

                continue;
            }

            // Not a refusal — the row was closed because the account had
            // already gone by another path. Reporting it as deferred would put
            // a permanent-looking warning on a queue item that is finished.
            if ($outcome === TenantDeletionOutcome::AlreadyGone) {
                // ⚠️ **THE SENTENCE SAID "THE REQUEST IS CLOSED" AND ONLY HALF OF
                // IT WAS** (2045). `execute()` sets `executed_at` on this path,
                // so the deletion row leaves `due()` and is never printed again
                // — while the §9.5 queue item that filed the erasure stayed
                // `AwaitingDeletion` forever, on a screen, with nothing left
                // that would ever close it. ⚠️ **The call that closes it is now
                // one call inside the `try` above** (8848), covering both paths
                // from one place.
                $this->info("Account {$reference} was already gone; the request is closed.");

                continue;
            }

            $deferred++;

            $this->warn(
                "Account {$reference} is due but was not deleted — "
                .$outcome->deferralMessage()
            );
        }

        $this->info("Deleted {$destroyed}, deferred {$deferred}.");

        // ⚠️ **AFTER THE LOOP, SO ONE BELL CARRIES THE RUN.** See the class
        // docblock: a bell per account is a bell per due account on the night
        // the database is unreachable.
        if ($failed !== []) {
            $this->ring($alerts, $failed);
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $failed  The `business_ref` of every erasure that raised.
     */
    private function ring(OperatorAlerts $alerts, array $failed): void
    {
        $count = count($failed);

        $alerts->raise(
            OperatorAlertKind::TenantErasureFailed,
            // One subject for the whole sweep — see the kind's own docblock.
            'tenants:execute-deletions',
            "{$count} account(s) due for erasure under 28 §9.5 could not be erased tonight and the "
            .'failure was not one this application expects. Nothing was destroyed, the requests are '
            .'still open, and the same set will fail the same way tomorrow. The exception class and '
            .'the account references are in the log.',
            [
                'failed' => $count,
                // References, never names. `28` §9.5's record is
                // `tenant_deletion_requests` and this is a pointer into it.
                'business_refs' => implode(',', $failed),
            ],
        );
    }
}
