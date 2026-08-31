<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\OwnerNotification;
use App\Models\OwnerReply;
use App\Models\User;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delete owner-channel rows past the period an operator has stated — wave 40
 * lane A, decision 10834.
 *
 * ⛔ **THE DEFECT: `owner_replies.body` WAS KEPT FOR EVER, AND THE MIGRATION
 * THAT CREATED IT CITED A RETENTION POLICY THAT DID NOT EXIST.** That file
 * argues for the tenant-owned design partly because there is *"a retention
 * policy to apply to it"*. There was not: fourteen `Prune*` commands existed
 * and none touched this table, and the only retention keys in the registry were
 * `automation.retention_days`, `fetch.attempts_retention_days` and
 * `review_loss.snapshot_retention_days`. **The free text of an account holder's
 * own messages left only by the `business_id` cascade** — `CLAUDE.md`'s
 * 314–316 shape, where the paragraph asserting a protection is the argument
 * used to justify creating the column.
 *
 * ⛔ **ON EVERY DEPLOYMENT THAT EXISTS THIS COMMAND DELETES NOTHING AND SAYS
 * SO.** `owner_channel.retention_days` ships with no seed, and **an unset
 * period is a no-op rather than a zero**. It is scheduled anyway, on
 * `PruneStoredObjects`' precedent: a sweep that only starts being scheduled on
 * the day a period is set is a second thing to remember on that day.
 *
 * ⚠️ **AND THE PERIOD IS WITHHELD FOR `storage.retention_days.*`'s STATED
 * REASON RATHER THAN BY HABIT.** That key's own manifest note: the drafted
 * privacy policy *"commits to a period for pre-signup audits and to nothing
 * about this kind"*, and `docs/LEGAL-DRAFTS-V1.md` lists retention periods
 * beyond the two the code enforces as counsel's to settle. This is an account
 * holder's own free text; a plausible figure invented here would look exactly
 * like a decision the moment it was read back out of the database.
 *
 * ## ⚠️ ONE PERIOD FOR BOTH TABLES
 *
 * They are one conversation. `owner_notifications` exists so that a stored
 * reply has something it can be an answer to (10820), and a deployment that
 * kept one half and swept the other would hold records that read as a reply to
 * nothing in one direction and a question nobody answered in the other. **One
 * number is also one decision for the owner rather than two**, and a second
 * knob nobody sets is a second permanent no-op (511's shape).
 *
 * ## The owner walk, and why it is not a range DELETE
 *
 * `PruneAutomationRuns`' shape and its reason: both tables are `ENABLE`+`FORCE`
 * row-level secured on `app.business_id`, so
 * `delete from owner_replies where created_at < ?` issued with no tenant
 * established **matches zero rows and exits 0** — and a pruner is the worst
 * possible host for that failure, because *"deleted nothing"* is also what a
 * healthy night looks like. Every test that proves the delete works drives it
 * through this command rather than through a method called inside
 * `Tenancy::actingAs()`, where the boundary cannot be seen failing at all.
 *
 * ⚠️ **A FAILED PRUNE IS NOT A FAILED RUN** (7630). One tenant's lock
 * contention must not cost every account after it in the walk its sweep.
 *
 * ## ⚠️ Two things this deliberately does not do
 *
 * **It does not date a row by `received_at`.** That column is what the carrier
 * says the handset sent, is nullable because the field is optional in the
 * webhook, and is not a fact about how long *we* have held the row.
 * `owner_replies.created_at` and `owner_notifications.sent_at` are.
 * ⚠️ **A NULL `created_at` IS KEPT, AND THAT IS THE FAIL-CLOSED DIRECTION** —
 * a row we cannot date is a row we cannot prove is past the period. Nothing in
 * `app/` writes one; a fixture can.
 *
 * **It does not reach the model's `deleting` guard, and it must not.**
 * `App\Models\OwnerReply` refuses a hand-written delete; `Eloquent\Builder::
 * delete()` is `$this->toBase()->delete()` and fires no model event, which is
 * `CLAUDE.md`'s `$guarded` finding on the delete axis. **The guard was never
 * able to refuse a mass delete**, and that model's docblock is corrected rather
 * than the guard being removed (10831).
 */
#[Signature('owner-channel:prune')]
#[Description('Delete owner-channel replies and notifications past the period stated in Ops')]
final class PruneOwnerChannel extends Command
{
    /**
     * The registry key holding how many days a row is kept.
     */
    public const string RETENTION_KEY = 'owner_channel.retention_days';

    public function handle(DefaultsRegistry $defaults): int
    {
        // Never inherit a tenant from whatever ran before this in the process.
        Tenancy::forgetAll();

        $stated = $defaults->intOr(self::RETENTION_KEY, 0);

        if ($stated <= 0) {
            $this->line(
                'No retention period is set for the owner channel (key: '
                .self::RETENTION_KEY.'), so nothing was deleted. '
                .'Set it in Ops to start pruning.'
            );

            return self::SUCCESS;
        }

        $cutoff = CarbonImmutable::now()->subDays($stated);

        $replies = 0;
        $notifications = 0;
        $accounts = 0;
        $refused = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (
                &$replies,
                &$notifications,
                &$accounts,
                &$refused,
                $cutoff
            ): void {
                foreach ($users as $user) {
                    [$sweptReplies, $sweptNotices, $seen, $failed] = $this->sweepOwner((int) $user->getKey(), $cutoff);

                    $replies += $sweptReplies;
                    $notifications += $sweptNotices;
                    $accounts += $seen;
                    $refused += $failed;
                }
            });

        // The PostgreSQL session variable outlives this process's connection
        // under any pooler, and a worker inheriting it would start as whichever
        // tenant this loop happened to touch last.
        Tenancy::forgetAll();

        $this->info(
            'Pruned '.$replies.' owner '.str('reply')->plural($replies)
            .' and '.$notifications.' owner '.str('notification')->plural($notifications)
            ." older than {$stated} days across {$accounts} ".str('account')->plural($accounts).'.'
        );

        if ($refused > 0) {
            // ⚠️ AFTER the line above rather than instead of it —
            // `PruneStoredObjects`' rule: "nothing was deleted" and "something
            // could not be deleted" are separate facts, and collapsing them
            // hides the one an operator would act on.
            $this->warn(
                $refused.' '.str('account')->plural($refused)
                .' could not be swept — see the log for the exception. The rows are '
                .'left exactly as they were, so tomorrow\'s run tries again.'
            );
        }

        return self::SUCCESS;
    }

    /**
     * Every business this user owns, through the `owner_lookup` policy.
     *
     * @return array{0: int, 1: int, 2: int, 3: int} replies, notifications,
     *                                               accounts visited, refused
     */
    private function sweepOwner(int $userId, CarbonImmutable $cutoff): array
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and no
        // tenant is established yet — the circularity `ResolveTenant` documents.
        // The database still restricts this to businesses owned by the user just
        // set, so it cannot widen beyond one person's own.
        $businessIds = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->pluck('id');

        $replies = 0;
        $notifications = 0;
        $refused = 0;

        foreach ($businessIds as $businessId) {
            try {
                [$sweptReplies, $sweptNotices] = Tenancy::actingAs(
                    (int) $businessId,
                    function () use ($cutoff): array {
                        // ⛔ **REPLIES FIRST.** A reply outliving its
                        // notification degrades to `NULL` through the foreign
                        // key, which is designed for and harmless; doing it in
                        // this order simply means it happens less.
                        $sweptReplies = OwnerReply::query()
                            ->where('created_at', '<', $cutoff)
                            ->delete();

                        $sweptNotices = OwnerNotification::query()
                            ->where('sent_at', '<', $cutoff)
                            ->delete();

                        return [$sweptReplies, $sweptNotices];
                    },
                );

                $replies += $sweptReplies;
                $notifications += $sweptNotices;
            } catch (Throwable $e) {
                $refused++;

                // ⚠️ THE CLASS NAME AND OUR OWN ID, NEVER A WORD OF WHAT WAS
                // SAID — every writer on this path's identical rule.
                Log::warning('owner-channel rows could not be pruned', [
                    'business_id' => $businessId,
                    'exception' => $e::class,
                ]);
            }
        }

        return [$replies, $notifications, $businessIds->count(), $refused];
    }
}
