<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\User;
use App\Services\Export\ExportBuilder;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Delete the export ZIPs whose seven days are up (1902).
 *
 * ## ⚠️ AN `expires_at` NOTHING ACTS ON IS NOT A RETENTION POLICY
 *
 * `PrunePublicAudits`' sentence, and the artifact here is far worse than an
 * audit row: one object is a complete, unencrypted copy of every contact's name,
 * email, phone and consent state that this account has. `28` §3.7 promises the
 * link expires in seven days; without this command the *link* expired and the
 * *file* did not, and `29` §2 rule 25 does not distinguish the two.
 *
 * ⚠️ **THE ROW GOES WITH THE OBJECT, AND ONLY IF THE OBJECT WENT.**
 * {@see ExportBuilder::purgeExpired()} holds that ordering and the reasoning for
 * it. Nothing is lost by removing the row: `audit_log` keeps `export.requested`,
 * `export.built` — with the byte size and the whole manifest in its metadata —
 * and `export.downloaded` for every fetch, so the append-only record of what
 * left the building outlives the artifact by design.
 *
 * ## ⚠️ THE SIXTH ENUMERATION SWEEP, AND IT IS THE FIRST ONE THAT DELETES
 *
 * `RefreshOauthTokens`' owner walk, copied for the sixth time — users, then each
 * user's businesses through the `owner_lookup` policy, then the tenant set for
 * the work. `tenant_exports` is `ENABLE`+`FORCE`d on `app.business_id`, so there
 * is no cross-tenant read to be had and no version of this that lists every
 * expired export in one query. `TenancyTest.php`'s `withoutGlobalScope` lint
 * carries the standing note that six is well past the point the shape should be
 * extracted behind one enumerator; it is not extracted here for that note's own
 * two reasons, plus one specific to this file — this is the only member of the
 * family whose mistake destroys data rather than skipping work, so it is the
 * worst possible first customer for an untested refactor of the other five.
 *
 * ⚠️ **A FAILED DELETE IS NOT A FAILED RUN.** An unreachable bucket leaves the
 * object and its row exactly where they are and tomorrow's run tries again.
 * `ExecuteTenantDeletions`' rule: the scheduler discards output, the only signal
 * is an exit code, and a command that reddens on a transient condition is one
 * whose red is ignored inside a week.
 *
 * ⚠️ **BUT IT IS NOT A SILENT RUN EITHER, AND IT USED TO BE WORSE THAN SILENT**
 * (1993). {@see ExportBuilder::deleteObject()} swallows every `Throwable` and
 * {@see ExportBuilder::purgeExpired()} used to `continue` past it returning only
 * the count removed — so with one expired export and an adapter throwing on
 * `delete()`, this command printed exactly *"No expired exports to prune."* and
 * exited 0. That output is **false**: there was something to prune and it was
 * not pruned, and the only human-facing signal affirmatively said otherwise
 * while §3.7's seven-day promise failed for every tenant at once. The refusals
 * are counted and warned on now, and the exit code still stays 0 — which is the
 * same shape `ExecuteTenantDeletions` already had, warning per deferral while
 * succeeding overall.
 */
#[Signature('exports:prune')]
#[Description('Delete "Download my data" ZIPs past their seven-day window')]
final class PruneTenantExports extends Command
{
    public function handle(ExportBuilder $exports): int
    {
        // Never inherit a tenant from whatever ran before this in the process.
        Tenancy::forgetAll();

        $purged = 0;
        $refused = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$purged, &$refused, $exports): void {
                foreach ($users as $user) {
                    [$swept, $stuck] = $this->sweepOwner((int) $user->getKey(), $exports);

                    $purged += $swept;
                    $refused += $stuck;
                }
            });

        // The PostgreSQL session variable outlives this process's connection
        // under any pooler, and a worker inheriting it would start as whichever
        // tenant this loop happened to touch last.
        Tenancy::forgetAll();

        $this->info($purged === 0
            ? 'No expired exports to prune.'
            : "Pruned {$purged} expired ".str('export')->plural($purged).'.');

        // ⚠️ AFTER the line above rather than instead of it, and both are true:
        // "nothing was pruned" and "something could not be pruned" are separate
        // facts, and collapsing them would leave a run that pruned nine and
        // failed on the tenth reporting only the nine.
        if ($refused > 0) {
            $this->warn(
                "{$refused} expired ".str('export')->plural($refused)
                .' could not be removed — the object store refused. '
                .'The rows are kept so tomorrow\'s run tries again; check the bucket credentials.'
            );
        }

        return self::SUCCESS;
    }

    /**
     * Every business this user owns, through the `owner_lookup` policy.
     *
     * @return array{0: int, 1: int} pruned, and refused by the object store
     */
    private function sweepOwner(int $userId, ExportBuilder $exports): array
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and no
        // tenant is established yet — the circularity `ResolveTenant` documents.
        // The database still restricts this to businesses owned by the user just
        // set, so it cannot widen beyond one person's own.
        $businessIds = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->pluck('id');

        $purged = 0;
        $refused = 0;

        foreach ($businessIds as $businessId) {
            [$swept, $stuck] = Tenancy::actingAs(
                (int) $businessId,
                fn (): array => $exports->purgeExpired(),
            );

            $purged += $swept;
            $refused += $stuck;
        }

        return [$purged, $refused];
    }
}
