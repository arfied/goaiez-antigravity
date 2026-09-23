<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\User;
use App\Services\Warehouse\WarehouseRetention;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Delete derived warehouse rows past their horizon — `GOAIEZ_PIXEL_MASTER_BUILD`
 * §18's *"L1 400 days"* and §5.4's *"400d / 24mo"* (decisions 7700–7719).
 *
 * ## ⛔ WHAT THIS CLOSES: WAVE 11 BOUNDED THE TRAFFIC WE REFUSE
 *
 * `pixel:prune-rejects` gave `ingest_rejects` a ninety-day horizon (7620–7639).
 * **Nothing bounded the traffic this platform accepts.** A batch whose events
 * are all `pageview` is admitted past `pixel.free_event_cap_monthly` for ever —
 * §11 row 4's *"pageviews continue"*, implemented faithfully — and the rate
 * limit is 300 beacons a minute **per source** at fifty events a batch. That is
 * 21.6 million conformed events a day from one host holding one public key,
 * into `l1_events`, which nothing had ever deleted a row from except a tenant
 * erasure. See {@see WarehouseRetention} for the whole argument, including why a
 * horizon on a **derived** layer is a different kind of decision from one on L0,
 * and why L0 itself is deliberately not swept here or anywhere.
 *
 * ## ⛔ THE EIGHTH OWNER WALK, AND IT IS LOAD-BEARING RATHER THAN CUSTOMARY
 *
 * `RefreshOauthTokens`' shape — users, each user's businesses through the
 * `owner_lookup` policy, then the tenant set for the work. ⛔ **On most members
 * of that family the walk is how a sweep finds its subjects. Here, as on
 * `PruneIngestRejects`, it is what makes the DELETE do anything at all**: every
 * derived table is `ENABLE`+`FORCE` row-level secured on `app.business_id` and
 * the application connects as a non-owner role, so a single
 * `delete from l1_events where received_at < ?` issued from a command with no
 * tenant set matches **zero rows and exits 0**. *"Deleted nothing"* is also what
 * a healthy night looks like, so the broken sweep and the working one print the
 * same sentence.
 *
 * ⚠️ **`businesses.owner_user_id` IS NOT NULL AND CASCADES**, so this walk
 * reaches every business that exists; there is no orphan set it silently skips.
 * Checked rather than assumed, exactly as `PruneIngestRejects` checked it.
 *
 * ⚠️ **A FAILED PRUNE IS NOT A FAILED RUN**, `PruneTenantExports`' rule.
 * {@see WarehouseRetention::prune()} contains and logs its own failure per table,
 * so one tenant's lock contention cannot cost the other tenants their sweep, and
 * the exit code stays `0` because *"a command that reddens on a transient
 * condition is one whose red is ignored inside a week"*.
 *
 * ⛔ **THIS PARAGRAPH USED TO CONTINUE *"THE COST IS STATED RATHER THAN HIDDEN:
 * THIS COMMAND CANNOT TELL A TENANT WHOSE DELETE FAILED FROM A TENANT WITH
 * NOTHING TO DELETE … THE PER-TABLE FAILURE IS A `warning` IN THE LOG AND
 * NOWHERE ELSE"* — CORRECTED 2026-08-22 (7840–7859).** Stating a cost is not the
 * same as paying it, and the cost was **decision 1993 verbatim**: with every
 * DELETE raising, this command printed *"No derived warehouse rows past their
 * horizon across N accounts."* and exited 0 — the same sentence a healthy night
 * prints, and affirmatively false, because there **was** something past the
 * horizon and none of it was removed. ✅ `prune()` now returns `failed`, this
 * command sums it, and the warning below is printed **after** the count line
 * rather than instead of it: *"nothing was pruned"* and *"something could not be
 * pruned"* are two facts, and collapsing them leaves a run that pruned nine and
 * failed on the tenth reporting only the nine.
 *
 * ⛔ **AND WHAT IS STILL TRUE IS THAT NOBODY IS WATCHING THE WARNING.** The
 * scheduled run's output goes to cron, no entry in `routes/console.php` wires an
 * `onFailure()`, and a bell needs an `App\Enums\OperatorAlertKind` case paired
 * with its raiser in one slice (7023). **Owed, and not this lane's.**
 *
 * ⚠️ **IT NAMES NO DERIVED TABLE AND NO DERIVED MODEL IN CODE, DELIBERATELY.**
 * `WarehouseTest`'s *"nothing outside the warehouse writes to a derived layer"*
 * matches those literals in stripped source anywhere under `app/`, and it is
 * right to: §5.1's truncate-and-rebuild property is a claim about every writer.
 * The reach lives in `App\Services\Warehouse`, where that lint permits it and
 * where `DerivedTables::DERIVED_TABLES` already enumerates the subject.
 */
#[Signature('warehouse:prune')]
#[Description('Delete derived warehouse rows past their retention horizon')]
final class PruneWarehouse extends Command
{
    public function handle(WarehouseRetention $retention): int
    {
        // Never inherit a tenant from whatever ran before this in the process.
        Tenancy::forgetAll();

        $deleted = ['l1' => 0, 'l2' => 0, 'failed' => 0];
        $accounts = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$deleted, &$accounts, $retention): void {
                foreach ($users as $user) {
                    [$swept, $seen] = $this->sweepOwner((int) $user->getKey(), $retention);

                    $deleted['l1'] += $swept['l1'];
                    $deleted['l2'] += $swept['l2'];
                    $deleted['failed'] += $swept['failed'];
                    $accounts += $seen;
                }
            });

        // The PostgreSQL session variable outlives this process's connection
        // under any pooler, and a worker inheriting it would start as whichever
        // tenant this loop happened to touch last.
        Tenancy::forgetAll();

        $total = $deleted['l1'] + $deleted['l2'];

        $this->info($total === 0
            ? 'No derived warehouse rows past their horizon across '.$accounts.' '
                .str('account')->plural($accounts).'.'
            : 'Pruned '.$deleted['l1'].' conformed '.str('event')->plural($deleted['l1'])
                .' past '.WarehouseRetention::L1_RETENTION_DAYS.' days and '.$deleted['l2'].' rollup '
                .str('row')->plural($deleted['l2']).' past '.WarehouseRetention::l2RetentionDays()
                .' days across '.$accounts.' '.str('account')->plural($accounts).'.');

        // ⚠️ AFTER the line above rather than instead of it — `PruneTenantExports`'
        // rule, and 1993's. Both sentences are true at once, and a run that
        // pruned nine tables and could not reach the tenth must not report only
        // the nine. ⛔ Until 2026-08-22 this branch did not exist and the failure
        // was a `warning` in the log and nowhere else, which is how "no rows past
        // their horizon" came to be the sentence a total outage prints.
        if ($deleted['failed'] > 0) {
            $this->warn(
                $deleted['failed'].' derived warehouse '.str('table')->plural($deleted['failed'])
                .' could not be swept at all — the delete raised. Rows past their horizon are still '
                .'there and tomorrow\'s run tries again; the exception class is in the log, per table '
                .'and per account.'
            );
        }

        return self::SUCCESS;
    }

    /**
     * Every business this user owns, through the `owner_lookup` policy.
     *
     * @return array{0: array{l1: int, l2: int, failed: int}, 1: int} rows deleted
     *                                                                per layer,
     *                                                                tables that
     *                                                                could not be
     *                                                                swept, and
     *                                                                accounts
     *                                                                visited
     */
    private function sweepOwner(int $userId, WarehouseRetention $retention): array
    {

        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and no
        // tenant is established yet — the circularity `ResolveTenant` documents.
        // The database still restricts this to businesses owned by the user just
        // set, so it cannot widen beyond one person's own.
        $businessIds = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->pluck('id');

        $deleted = ['l1' => 0, 'l2' => 0, 'failed' => 0];

        foreach ($businessIds as $businessId) {
            $swept = Tenancy::actingAs(
                (int) $businessId,
                fn (): array => $retention->prune(
                    WarehouseRetention::L1_RETENTION_DAYS,
                    WarehouseRetention::l2RetentionDays(),
                ),
            );

            $deleted['l1'] += $swept['l1'];
            $deleted['l2'] += $swept['l2'];
            $deleted['failed'] += $swept['failed'];
        }

        return [$deleted, $businessIds->count()];
    }
}
