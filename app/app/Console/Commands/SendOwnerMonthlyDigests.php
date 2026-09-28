<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\User;
use App\Notifications\OwnerMonthlyDigest;
use App\Services\Activity\MonthlyDigest;
use App\Services\Config\DefaultsRegistry;
use App\Services\Mail\PlatformMailer;
use App\Support\MailFailure;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Automation #109's monthly email.
 *
 * ## The enumeration follows its siblings, and for their reasons
 *
 * This runs outside any tenant, and `businesses` is FORCE ROW LEVEL SECURITY
 * on a policy keyed to the session tenant — so `Business::all()` returns
 * nothing, and dropping a global scope does not help, because the policy is
 * in the database. Reaching each business through its owner via
 * `owner_lookup` grants this sweep no privilege a logged-in owner does not
 * already have. Read `SendRenewalReminders` and `AdvanceDunningSchedules`
 * before changing it — this command is that exact shape, for a report rather
 * than for money.
 *
 * ## DAILY, THOUGH IT SENDS AT MOST ONCE A MONTH PER BUSINESS
 *
 * `MonthlyDigest::eligible()` decides per business, from a cursor on the
 * business's own row, never from a shared platform schedule. On the twenty-nine
 * days nothing is due for a business, this reads one row and writes nothing.
 *
 * ## A per-business failure never stops the sweep — and never disappears
 * either
 *
 * ⛔ **NOTHING NEW WAS BUILT TO ALERT ON A FAILURE, AND THAT IS ARGUED RATHER
 * THAN AN OVERSIGHT** (decision 10733). One existing generic mechanism covers
 * it: `ScheduledRunMeter`'s listener rings
 * `OperatorAlertKind::ScheduledRunFailed` for any scheduled entry that exits
 * non-zero. This command's only new contribution is returning `self::FAILURE`
 * when any per-business attempt threw — which is what lets that mechanism see
 * a fault that would otherwise be a silently caught loop iteration.
 *
 * ✅ **WHAT STILL RINGS IS `ScheduledRunFailed`, AND IT IS ENOUGH HERE FOR A
 * STATED REASON**: a digest failure is a *scheduled sweep* failure, this
 * command returns `FAILURE` on any per-business throw, and the cursor does not
 * move — so tomorrow's run retries the same window.
 *
 * ⚠️ **WHAT IS LOST IS THE PER-MAILER DEDUPE AND THE NAMED TRANSPORT.**
 * `PlatformMailUndeliverable` names *which mailer* is broken, which is the
 * thing an operator goes and fixes; `ScheduledRunFailed` names this command.
 *
 * ⚠️ **`canDeliver()` STAYS IN FRONT OF IT AND THE TWO ARE NOT DUPLICATES.**
 * `canDeliver()` answers *is this deployment's mail system configured at all* —
 * a fact about `.env` that is identical for every business in the sweep and is
 * not a per-business fault, so it skips without counting a failure and without
 * moving the cursor. `deliverNow()` answers *was this message accepted*.
 */
#[Signature('owners:send-monthly-site-digest')]
#[Description('Send the monthly "what your website did" email to every account holder who is due one')]
final class SendOwnerMonthlyDigests extends Command
{
    /**
     * The one switch over the whole sweep — `owner_digest.monthly_enabled`.
     *
     * ⛔ **ASKED BEFORE THE WALK AND NOT PER BUSINESS**, because it is a
     * platform fact rather than a tenant one.
     *
     * ⚠️ **EXIT ZERO, DELIBERATELY.** A switch an operator set is not a fault.
     */
    public function handle(MonthlyDigest $digest, PlatformMailer $mailer, DefaultsRegistry $defaults): int
    {
        if ($defaults->value('owner_digest.monthly_enabled') !== true) {
            $this->info('The monthly digest is switched off, so nobody was sent one.');

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use ($digest, $mailer, &$sent, &$failed): void {
                foreach ($users as $user) {
                    [$sentForUser, $failedForUser] = $this->digestForOwner((int) $user->getKey(), $digest, $mailer);
                    $sent += $sentForUser;
                    $failed += $failedForUser;
                }
            });

        // Never leave a security context established after a console command
        // — the PostgreSQL session variable outlives this process's
        // connection under any pooler, and a worker inheriting it would start
        // as whichever tenant the loop happened to touch last.
        Tenancy::forgetAll();

        $this->info($sent === 0
            ? 'No monthly digests were due.'
            : "Sent {$sent} monthly ".str('digest')->plural($sent).'.');

        if ($failed > 0) {
            $this->error("{$failed} ".str('business')->plural($failed).
                ' could not be checked or sent to; see the log.');
        }

        // ⚠️ **NON-ZERO ON ANY FAILURE, EVEN THOUGH SENDING CONTINUED FOR
        // EVERY OTHER BUSINESS.**
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int} sent, failed
     */
    private function digestForOwner(int $userId, MonthlyDigest $digest, PlatformMailer $mailer): array
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and
        // no tenant is established yet.
        $businesses = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($businesses as $business) {
            try {
                $didSend = Tenancy::actingAs(
                    (int) $business->id,
                    fn (): bool => $this->digestForOne($business, $digest, $mailer),
                );

                if ($didSend) {
                    $sent++;
                }
            } catch (Throwable $e) {
                // The ordinary exception at this line is an SMTP refusal.
                $failed++;

                Log::warning('A monthly site digest could not be checked or sent.', [
                    ...MailFailure::logContext($e),
                    'business_id' => $business->id,
                ]);
            }
        }

        return [$sent, $failed];
    }

    private function digestForOne(Business $business, MonthlyDigest $digest, PlatformMailer $mailer): bool
    {
        if (! $digest->eligible($business)) {
            return false;
        }

        $content = $digest->compose($business);

        if ($content === null) {
            // ⚠️ **DELIBERATELY DOES NOT ADVANCE `owner_monthly_digest_sent_at`**.
            return false;
        }

        $address = $business->owner?->email;

        if (! is_string($address) || $address === '') {
            return false;
        }

        if (! $mailer->canDeliver()) {
            return false;
        }

        // ⛔ **`deliverNow()` RATHER THAN `send()`, AND THE `update()` BELOW IS
        // WHY**.
        $mailer->deliverNow($address, new OwnerMonthlyDigest(
            businessName: (string) $business->name,
            label: $content['label'],
            pages: $content['pages'],
            visits: $content['visits'],
            lines: $content['lines'],
            activityUrl: route('account.activity'),
        ));

        // ⚠️ **AFTER THE SEND RETURNS, NEVER BEFORE IT**.
        // ⚠️ **`Model::query()->update()` AND NOT `$business->update()`, AND
        // `$guarded` IS NOT WHAT MAKES THAT SAFE**.
        Business::query()->whereKey($business->getKey())->update([
            'owner_monthly_digest_sent_at' => $content['to'],
        ]);

        return true;
    }
}
