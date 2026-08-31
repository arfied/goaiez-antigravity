<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\User;
use App\Notifications\OwnerWeeklyDigest;
use App\Services\Activity\OwnerDigest;
use App\Services\Config\DefaultsRegistry;
use App\Services\Mail\MailQuota;
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
 * Automation #109's email half — the daily check that decides who is due a
 * weekly wins digest. Wave 39 lane C, decision rows 10726–10733.
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
 * ## DAILY, THOUGH IT SENDS AT MOST ONCE A WEEK PER BUSINESS
 *
 * `OwnerDigest::eligible()` decides per business, from a cursor on the
 * business's own row, never from a shared platform schedule — decision 10728,
 * `credits:reset-monthly`'s own self-healing argument. On the twenty-nine
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
 * ⛔ **THIS NAMED *TWO* MECHANISMS, AND THE SECOND STOPPED COVERING THIS
 * COMMAND IN THE SAME COMMIT THAT REWROTE THE PARAGRAPH DIRECTLY BELOW IT —
 * CORRECTED 2026-08-28 (wave 41 lane D, 11082).** It read *"…and
 * `OperatorAlertKind::PlatformMailUndeliverable` is rung for a message that
 * cannot be delivered, deduped on `OperatorAlerts::rangSince()`."* **That bell
 * is raised in exactly one place — `App\Jobs\DeliverPlatformMail`'s
 * `failed()` hook** — and 10852 moved this command off `send()`, so it never
 * enters that job and the hook can never run for it. `deliverNow()` rings
 * nothing: it throws, and the throw is what this command counts.
 * ⚠️ **The two paragraphs are adjacent and one falsified the other**, which is
 * 10971's shape one file over, and why `_COMMON.md` requires a lane that moves
 * a caller to say what still rings.
 * ✅ **WHAT STILL RINGS IS `ScheduledRunFailed`, AND IT IS ENOUGH HERE FOR A
 * STATED REASON**: a digest failure is a *scheduled sweep* failure, this
 * command returns `FAILURE` on any per-business throw, and the cursor does not
 * move — so tomorrow's run retries the same week
 * ({@see OwnerDigest::windowStart()}'s self-heal) and the bell is about the
 * sweep rather than about one message.
 * ⚠️ **WHAT IS LOST IS THE PER-MAILER DEDUPE AND THE NAMED TRANSPORT.**
 * `PlatformMailUndeliverable` names *which mailer* is broken, which is the
 * thing an operator goes and fixes; `ScheduledRunFailed` names this command.
 * On a wholly broken transport both still fire, from different paths — but if
 * `ScheduledRunMeter`'s listener were ever narrowed, this command would have
 * **no** bell rather than one fewer. **Stated here rather than left to be
 * discovered.**
 *
 * ⛔ **AND UNTIL DECISION 10852 THE SECOND HALF OF THAT PARAGRAPH WAS TRUE OF
 * NOTHING THIS COMMAND COULD SEE, AND THE FIRST HALF COULD NOT FIRE.**
 * `PlatformMailer::send()` is a bare `DeliverPlatformMail::dispatch()` wrapped
 * in a `catch (Throwable)` that logs and returns **void** (9500). So a message
 * that was never handed to the queue at all — the `jobs` table unwritable,
 * Redis refusing a connection — was indistinguishable here from one that went
 * out, `$failed` stayed zero, this command exited **0**, and
 * {@see self::digestForOne()} then advanced the cursor and put that week
 * permanently outside every future window. **That is `CLAUDE.md`'s 9371 in
 * full: a column that records a dispatch, read as a column that records a
 * delivery.** ⚠️ **And the loss is worse here than on the `operator_alerts`
 * row 9371 was about**, because this cursor is not a report on the send — it
 * **is** the window, so a wrong stamp deletes a week of a tenant's history
 * from the only email that would ever have named it.
 *
 * ⚠️ **THE REPAIR IS 9371's OWN AND IT LEVELS UP RATHER THAN DOWN.**
 * `OperatorAlerts::email()` had exactly this column and exactly this defect and
 * was moved from `send()` to `deliverNow()`, so the stamp means *the transport
 * accepted it*. This does the same. The tidy-minded edit in the other
 * direction — advancing the cursor on the skip path too, so that all arms
 * "agree" — reads on a diff as removing an inconsistency and would delete
 * decision 10730 as well.
 *
 * ⚠️ **WHAT IS GIVEN UP IS `DeliverPlatformMail`'s RETRY LADDER, AND THE DAILY
 * RE-RUN IS A BETTER ONE.** A queued retry re-attempts the same composed
 * message against a cursor that has already moved; this command's own cadence
 * re-attempts tomorrow against a cursor that has not, so the window widens to
 * cover the days the failed attempt would have reported —
 * {@see OwnerDigest::windowStart()}'s self-heal,
 * doing the job a `$backoff` array would have done worse.
 *
 * ⚠️ **`canDeliver()` STAYS IN FRONT OF IT AND THE TWO ARE NOT DUPLICATES.**
 * `canDeliver()` answers *is this deployment's mail system configured at all* —
 * a fact about `.env` that is identical for every business in the sweep and is
 * not a per-business fault, so it skips without counting a failure and without
 * moving the cursor. `deliverNow()` answers *was this message accepted*, which
 * includes the two refusals `canDeliver()` structurally cannot see: a daily
 * send ceiling nobody has stated — {@see MailQuota::ceiling()}
 * owns that key and the `smtp` mailer carries no seed for it, so an unstated
 * ceiling is the state of a fresh deployment — and a ceiling already reached.
 * Both are driven directly.
 */
#[Signature('owners:send-weekly-digest')]
#[Description('Send the weekly wins digest email to every account holder who is due one')]
final class SendOwnerWeeklyDigests extends Command
{
    /**
     * The one switch over the whole sweep — `owner_digest.enabled`, decision
     * 10857.
     *
     * ⛔ **ASKED BEFORE THE WALK AND NOT PER BUSINESS**, because it is a
     * platform fact rather than a tenant one: an operator turning this off in
     * an incident wants the sweep to stop, not to run and decline nine
     * thousand times. It also means an off switch costs one registry read
     * rather than an enumeration of every user in the system.
     *
     * ⚠️ **EXIT ZERO, DELIBERATELY.** A switch an operator set is not a fault,
     * and returning `self::FAILURE` here would ring
     * `OperatorAlertKind::ScheduledRunFailed` every morning for as long as it
     * stayed off. ⚠️ **What stops that being a silent stop** — 9370's shape —
     * is that the state is visible in two places without anybody remembering
     * it: the row itself on the Ops registry screen, and this line, printed on
     * every run.
     *
     * ⚠️ **AND SWITCHING IT OFF LOSES NOTHING**, which is what makes an off
     * switch on a *report* safe at all: `digestForOne()` moves the cursor only
     * on a delivery (10852), so the weeks spent off are still inside the
     * window when it comes back on — bounded by `OwnerDigest::MAX_WINDOW_DAYS`
     * (10850) and named honestly in the subject line (10851).
     */
    public function handle(OwnerDigest $digest, PlatformMailer $mailer, DefaultsRegistry $defaults): int
    {
        if ($defaults->value('owner_digest.enabled') !== true) {
            $this->info('The weekly digest is switched off, so nobody was sent one.');

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
            ? 'No digests were due.'
            : "Sent {$sent} weekly ".str('digest')->plural($sent).'.');

        if ($failed > 0) {
            $this->error("{$failed} ".str('business')->plural($failed).
                ' could not be checked or sent to; see the log.');
        }

        // ⚠️ **NON-ZERO ON ANY FAILURE, EVEN THOUGH SENDING CONTINUED FOR
        // EVERY OTHER BUSINESS.** This is the one line that gives the
        // existing `ScheduledRunFailed` bell something to see — see this
        // class's own docblock.
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int} sent, failed
     */
    private function digestForOwner(int $userId, OwnerDigest $digest, PlatformMailer $mailer): array
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and
        // no tenant is established yet — the same circularity ResolveTenant
        // documents. The database still restricts this to businesses owned by
        // the user just set, so it cannot widen beyond one person's own.
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
                // ⛔ **THIS SAID *"AN EXCEPTION HERE IS A BUG IN THIS SWEEP OR A
                // DATABASE FAULT, NEVER SOMETHING A CUSTOMER WROTE"* AND THE
                // COMMONEST OCCUPANT IS NEITHER — 11339.** {@see self::digestForOne()}
                // calls `deliverNow()`, and this file's own comment above that
                // call says so: *"this throws if the transport refuses … the
                // throw is caught one frame up."* **So the ordinary exception
                // at this line is an SMTP refusal**, and the premise the safety
                // was argued from was false while the code it guarded was
                // right — 11171's shape, three files along from where 11171 was
                // written.
                //
                // ⚠️ **THE CONCLUSION SURVIVES THE PREMISE AND IS NOT WEAKENED.**
                // Nothing a customer wrote reaches here either way; what changes
                // is that the reason is now true.
                //
                // ✅ **AND THE CODE IS CARRIED, WHICH THE CLASS NAME COULD NOT
                // GIVE.** `UnexpectedResponseException` is the class for every
                // SMTP refusal, so an operator reading this line could not tell
                // a `421` to wait out from a `554` that needs somebody in the
                // AWS console. `logContext()`'s transport arm is three digits
                // and no text.
                $failed++;

                Log::warning('A weekly owner digest could not be checked or sent.', [
                    ...MailFailure::logContext($e),
                    'business_id' => $business->id,
                ]);
            }
        }

        return [$sent, $failed];
    }

    private function digestForOne(Business $business, OwnerDigest $digest, PlatformMailer $mailer): bool
    {
        if (! $digest->eligible($business)) {
            return false;
        }

        $content = $digest->compose($business);

        if ($content === null) {
            // ⚠️ **DELIBERATELY DOES NOT ADVANCE `owner_digest_sent_at`** —
            // decision 10730. The window re-derives from the same cursor
            // tomorrow, so a quiet week costs nothing and loses nothing if
            // activity resumes mid-week.
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
        // WHY** — decision 10852, `OperatorAlerts::email()`'s own repair. This
        // throws if the transport refuses, which is what stops the cursor
        // being stamped for a message nobody ever accepted; the throw is
        // caught one frame up, counted, and turned into the non-zero exit the
        // `ScheduledRunFailed` bell reads. See this class's own docblock.
        $mailer->deliverNow($address, new OwnerWeeklyDigest(
            businessName: (string) $business->name,
            since: $content['since'],
            lines: $content['lines'],
            overflow: $content['overflow'],
            activityUrl: route('account.activity'),
        ));

        // ⚠️ **AFTER THE SEND RETURNS, NEVER BEFORE IT** — `MailQuota::record()`'s
        // own ordering, for the same reason. A cursor moved first is a week
        // spent on a message the transport had not taken yet.
        //
        // ⚠️ **`Model::query()->update()` AND NOT `$business->update()`, AND
        // `$guarded` IS NOT WHAT MAKES THAT SAFE** (9140–9143). The bare-key
        // builder is one of the six write shapes that evade `$guarded`
        // entirely; what keeps this column off an owner-facing screen is that
        // no such screen writes it, and the guard covers only the request-
        // shaped mass assignment that would.
        Business::query()->whereKey($business->getKey())->update([
            'owner_digest_sent_at' => now(),
        ]);

        return true;
    }
}
