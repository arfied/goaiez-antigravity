<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\User;
use App\Services\Billing\TrialReminders;
use App\Support\MailFailure;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The daily sweep that tells a trial owner their trial is running out (9395).
 *
 * ⛔ **WITHOUT THIS COMMAND AND ITS LINE IN `routes/console.php`, THE WHOLE
 * LADDER IS WHAT IT HAS BEEN SINCE 5258: FINISHED COPY, A COMPOSER, TWO
 * ARCHITECTURE LINTS AND NO SENDER.** That is `CLAUDE.md`'s most-repeated
 * failure, and shipping {@see TrialReminders} without the schedule would have
 * reproduced it one layer up — a service with a caller in `tests/` and none in
 * production, which reads exactly like a service that works.
 *
 * ## The enumeration follows its siblings, and for their reasons
 *
 * This runs outside any tenant, and `businesses` is FORCE ROW LEVEL SECURITY on
 * a policy keyed to the session tenant — so `Business::all()` returns nothing,
 * and dropping a global scope does not help, because the policy is in the
 * database. Reaching each business through its owner via `owner_lookup` grants
 * this sweep no privilege a logged-in owner does not already have. Read
 * {@see SendRenewalReminders} before changing it; this is that walk exactly.
 *
 * ⚠️ **NO PRE-FILTER, FOR `SendRenewalReminders`' REASON.** It runs once a day
 * and its check is one subscription read on a business it has to load anyway.
 *
 * ⚠️ **`runInBackground()` IS TAKEN.** The two commands in this file that refuse
 * it do so because their work is irreversible or is a platform-wide switch.
 * Sending a trial warning is neither, and a slow mailer must not delay the rest
 * of the schedule.
 *
 * ⚠️ **THE COUNT IS RUNGS SENT AND NOT ACCOUNTS WALKED**, because the number an
 * operator wants at 06:00 is *how many people were told something*. A quiet day
 * is the ordinary case: on a fourteen-day trial each account matches a rung on
 * four days out of its whole life.
 *
 * ## ⛔ A per-business refusal never stops the sweep, and never disappears either
 *
 * ⛔ **THIS COMMAND HAD NO `try` AT ALL AND `remind()` COULD NOT THROW, WHICH
 * WAS ONE FACT RATHER THAN TWO — 10992.** {@see TrialReminders::remind()} now
 * sends through `PlatformMailer::deliverNow()`, so an uncaught refusal on one
 * business would end the walk and every account after it in `users.id` order
 * would hear nothing that day. That is the argument `TrialReminders` already
 * makes at its own composition `catch`, arriving one frame up.
 *
 * ⛔ **AND NOTHING NEW IS BUILT TO ALERT ON IT.** The count turns into a
 * non-zero exit and `App\Services\Ops\ScheduledRunMeter` rings
 * `App\Enums\OperatorAlertKind::ScheduledRunFailed` — the `backgroundFinished()`
 * arm, because this entry takes `runInBackground()` and `ScheduleRunCommand`
 * never dispatches `ScheduledTaskFailed` for one of those.
 * ⛔ **`OperatorAlertKind::PlatformMailUndeliverable` no longer covers this
 * sweep**, because it is raised only inside `App\Jobs\DeliverPlatformMail`'s own
 * `failed()`.
 *
 * ⚠️ **A REFUSED RUNG IS USUALLY NOT LOST**, which is the difference between
 * this sweep and the renewal one: `remind()` gives the claim back on any
 * `MailNotDeliverable`, so tomorrow's run sends whichever rung is due tomorrow
 * against a row that has not moved.
 */
#[Signature('billing:send-trial-reminders')]
#[Description("Send today's rung of the no-card free trial ladder to every owner it is due for")]
final class SendTrialReminders extends Command
{
    public function handle(TrialReminders $reminders): int
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
            ? 'No trial reminders are due.'
            : "Sent {$sent} trial ".str('reminder')->plural($sent).'.');

        if ($failed > 0) {
            $this->error("{$failed} trial ".str('reminder')->plural($failed).
                ' could not be sent; see the log.');
        }

        // ⚠️ **NON-ZERO ON ANY REFUSAL, EVEN THOUGH THE SWEEP CONTINUED FOR
        // EVERY OTHER BUSINESS.** This is the one line the `ScheduledRunFailed`
        // bell reads — see this class's own docblock.
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int} sent, refused
     */
    private function remindForOwner(int $userId, TrialReminders $reminders): array
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
                $rung = Tenancy::actingAs(
                    $business->id,
                    fn () => $reminders->remind($business),
                );

                if ($rung !== null) {
                    $sent++;
                }
            } catch (Throwable $e) {
                $failed++;

                // ⛔ **THIS CATCH TAKES `Throwable` AND THE SENTENCE THAT USED
                // TO BE HERE REASONED ABOUT ONE CLASS** — 11170. It said *"the
                // class name, the reason and our own account number — never the
                // address"*, which is true of every `MailNotDeliverable`
                // factory and of nothing else that can arrive here. The class
                // {@see TrialReminders::remind()} deliberately does **not**
                // catch — a Symfony `UnexpectedResponseException` — carries the
                // mail server's verbatim reply in `getMessage()`, and on this
                // deployment that reply is `554 Message rejected: Email address
                // is not verified` followed by the identity that failed, which
                // is the account holder's own address.
                //
                // ⚠️ **THE COMMENT WAS NOT WRONG, IT WAS NARROWER THAN THE CODE
                // IT SAT ON**, which is the harder failure: every reader who
                // checked it against the case it names confirmed it.
                // {@see MailFailure} is where the three arms are decided and
                // where the argument for each is written.
                Log::warning('A trial ladder rung could not be sent.', [
                    ...MailFailure::logContext($e),
                    'business_id' => $business->id,
                ]);
            }
        }

        return [$sent, $failed];
    }
}
