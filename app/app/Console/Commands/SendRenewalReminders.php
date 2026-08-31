<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\User;
use App\Services\Billing\RenewalReminders;
use App\Support\MailFailure;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The daily sweep that sends California's pre-renewal notice (2980–2999).
 *
 * ⛔ **WITHOUT THIS COMMAND AND ITS LINE IN `routes/console.php`,
 * `subscriptions.renewal_reminded_for` IS A COLUMN NOTHING WRITES AND THE NOTICE
 * IS A DUTY NOTHING DISCHARGES.** That is `CLAUDE.md`'s most-repeated failure —
 * 2496–2499's `sending_health_windows`, 2590's `next_attempt_at`, 2578's
 * campaign runner with no trigger — and it is the shape this whole lane exists
 * to fix, so shipping the sender without the schedule would have reproduced it
 * inside the fix.
 *
 * ## The enumeration follows its siblings, and for their reasons
 *
 * This runs outside any tenant, and `businesses` is FORCE ROW LEVEL SECURITY on
 * a policy keyed to the session tenant — so `Business::all()` returns nothing,
 * and dropping a global scope does not help, because the policy is in the
 * database. Reaching each business through its owner via `owner_lookup` grants
 * this sweep no privilege a logged-in owner does not already have. Read
 * `AdvanceDunningSchedules` and `ReinviteDeferredReviews` before changing it.
 *
 * ⚠️ **NO PRE-FILTER, UNLIKE `AdvanceDunningSchedules`.** That command filters
 * because a dunning schedule is rare and it runs twenty-four times a day; this
 * one runs once a day and its check is two column reads on a row it has to load
 * anyway. Adding a filter would need the same query twice.
 *
 * ⚠️ **`runInBackground()` IS TAKEN, AND THE THING THAT WOULD ARGUE AGAINST IT
 * IS NOT TRUE HERE.** `tenants:execute-deletions` refuses it because its work is
 * irreversible and `messaging:watch-platform-complaint-rate` refuses it because
 * its work is a platform-wide switch. Sending a notice early is recoverable and
 * sending it twice is an annoyance, so the ordinary reason applies: a slow
 * mailer must not delay the rest of the schedule.
 *
 * ⚠️ **AND IT SENDS SYNCHRONOUSLY PER BUSINESS SINCE 10980**, which is what
 * makes that paragraph load-bearing rather than habitual:
 * {@see RenewalReminders::remind()} now calls `PlatformMailer::deliverNow()`, so
 * this walk holds an SMTP round trip per notice instead of a queue push. The
 * population is narrow — annual, bought outright, live, uncancelled, renewing in
 * 15–30 days — so the round trips are rare, and `runInBackground()` is what
 * keeps a slow one off the rest of the 05:30 window.
 *
 * ## ⛔ A per-business failure never stops the sweep, and never disappears either
 *
 * ⛔ **THIS COMMAND HAD NO `try` AT ALL AND `remind()` COULD NOT THROW, WHICH
 * WAS ONE FACT RATHER THAN TWO — 10980.** Now that the send can refuse, an
 * uncaught refusal on one business would end the walk and **every account after
 * it in `users.id` order would hear nothing that day** —
 * `TrialReminders`' own composition `catch` makes the same argument for the same
 * sweep shape.
 *
 * ⛔ **AND NOTHING NEW IS BUILT TO ALERT ON IT, WHICH IS ARGUED RATHER THAN
 * OVERLOOKED.** The count turns into a non-zero exit, and
 * `App\Services\Ops\ScheduledRunMeter` rings
 * `App\Enums\OperatorAlertKind::ScheduledRunFailed` for a scheduled entry that
 * exits non-zero. ⚠️ **The `backgroundFinished()` arm is the one that covers
 * this entry**: `ScheduleRunCommand` never dispatches `ScheduledTaskFailed` for
 * a `runInBackground()` event, so the foreground arm would have been silence.
 * ⛔ **`OperatorAlertKind::PlatformMailUndeliverable` no longer covers this
 * sweep at all** — it is raised only inside `App\Jobs\DeliverPlatformMail`'s
 * own `failed()`, and `deliverNow()` does not go near that job.
 */
#[Signature('billing:send-renewal-reminders')]
#[Description('Send the pre-renewal notice to every annual plan renewing in the statutory window')]
final class SendRenewalReminders extends Command
{
    public function handle(RenewalReminders $reminders): int
    {
        $sent = 0;
        $failed = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use ($reminders, &$sent, &$failed): void {
                foreach ($users as $user) {
                    [$sentForOwner, $failedForOwner] = $this->remindForOwner((int) $user->getKey(), $reminders);
                    $sent += $sentForOwner;
                    $failed += $failedForOwner;
                }
            });

        // Never leave a security context established after a console command —
        // the PostgreSQL session variable outlives this process's connection
        // under any pooler, and a worker inheriting it would start as whichever
        // tenant the loop happened to touch last.
        Tenancy::forgetAll();

        $this->info($sent === 0
            ? 'No renewal notices are due.'
            : "Sent {$sent} renewal ".str('notice')->plural($sent).'.');

        if ($failed > 0) {
            // ⛔ **THE WORD IS "STILL OWED", NOT "FAILED"**, because that is the
            // consequence an operator has to act on: the notice was not sent,
            // nothing recorded that it was, and tomorrow's run will try again
            // inside a window that closes 15 days before the renewal.
            $this->error("{$failed} renewal ".str('notice')->plural($failed).
                ' could not be sent and '.($failed === 1 ? 'is' : 'are').' still owed; see the log.');
        }

        // ⚠️ **NON-ZERO ON ANY FAILURE, EVEN THOUGH THE SWEEP CONTINUED FOR
        // EVERY OTHER BUSINESS.** This is the one line that gives the existing
        // `ScheduledRunFailed` bell something to see — see this class's own
        // docblock, and note that it is the meter's background arm that reads it.
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int} sent, still owed
     */
    private function remindForOwner(int $userId, RenewalReminders $reminders): array
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and no
        // tenant is established yet — the same circularity ResolveTenant
        // documents. The database still restricts this to businesses owned by
        // the user just set, so it cannot widen beyond one person's own.
        $businesses = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($businesses as $business) {
            try {
                $reminded = Tenancy::actingAs(
                    $business->id,
                    fn (): bool => $reminders->remind($business),
                );

                if ($reminded) {
                    $sent++;
                }
            } catch (Throwable $e) {
                $failed++;

                // ⛔ **THIS CATCH TAKES `Throwable` AND THE SENTENCE THAT USED
                // TO BE HERE REASONED ABOUT ONE CLASS** — 11170. It said *"the
                // class name, the reason and our own account number — never the
                // address"*, which is true of every `MailNotDeliverable`
                // factory and of nothing else that can arrive here.
                // `RenewalReminders::remind()` catches nothing at all, so a
                // Symfony `UnexpectedResponseException` reaches this line
                // carrying the mail server's verbatim reply — and on this
                // deployment that reply is `554 Message rejected: Email address
                // is not verified` followed by the identity that failed, which
                // is the account holder's own address.
                //
                // ⚠️ **THE COMMENT WAS NOT WRONG, IT WAS NARROWER THAN THE CODE
                // IT SAT ON**, which is the harder failure: every reader who
                // checked it against the case it names confirmed it.
                // {@see MailFailure} is where the three arms are decided and
                // where the argument for each is written.
                Log::warning('A pre-renewal notice could not be sent, and it is still owed.', [
                    ...MailFailure::logContext($e),
                    'business_id' => $business->id,
                ]);
            }
        }

        return [$sent, $failed];
    }
}
