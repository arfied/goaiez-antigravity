<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\StoredObjectKind;
use App\Models\Business;
use App\Models\User;
use App\Services\Export\ExportBuilder;
use App\Services\Storage\StorageRetention;
use App\Services\Storage\StorageSweep;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Delete stored objects past the period stated for their kind — decisions
 * 4940–4945, answering 4768.
 *
 * ⛔ **ON A DEPLOYMENT WHERE NOBODY HAS STATED A PERIOD, THIS COMMAND DELETES
 * NOTHING AND SAYS SO.** That is every deployment today. It is scheduled anyway,
 * and it is scheduled *because* it deletes nothing: a sweep that only starts
 * being scheduled on the day a period is set is a second thing to remember on
 * that day, and the whole point of 4942's split is that stating the period is
 * the only remaining step. See {@see StorageRetention}, which holds the
 * argument and the fail-closed property.
 *
 * ⛔ **IT PRINTS THE UNSET KEYS EVERY RUN, WHICH IS WHERE 272's ANSWER LIVES.**
 * `DefaultsManifest::withheld()` buys a refusal that names the decision, and a
 * withheld key cannot be supplied through Ops at all (4019, 4604) — so these
 * keys are declared-without-seed instead, and the visibility withheld would have
 * bought is bought here: an operator running this sees exactly which kinds are
 * being kept for ever and the key that would change it. A gap nobody can see is
 * the failure this codebase has seventeen recorded instances of.
 *
 * ⛔ **AND THAT SENTENCE IS TRUE OF *KINDS* AND WAS SILENT ABOUT A STORE THAT IS
 * NOT ONE.** The pixel event archive has no case, so no key, so no line — it was
 * kept for ever and appeared on no list this command prints.
 * {@see self::reportTheStoreThisSweepCannotReach()} is the line that ends that,
 * and it is pinned by `tests/Feature/Architecture/StorageTest.php`.
 *
 * ## ⚠️ THE SEVENTH ENUMERATION SWEEP, AND THE SECOND ONE THAT DELETES
 *
 * `RefreshOauthTokens`' owner walk again — users, each user's businesses through
 * the `owner_lookup` policy, then the tenant set for the work. All four tables
 * are `ENABLE`+`FORCE`d on `app.business_id`, so there is no cross-tenant query
 * to be had and no version of this that lists every expired object at once.
 * `PruneTenantExports` argues at length that a deleting sweep is the worst
 * possible first customer for an untested extraction of this shape; this is the
 * second one, and the argument has not weakened.
 *
 * ⚠️ **A FAILED DELETE IS NOT A FAILED RUN.** An unreachable bucket leaves the
 * object and its row where they are and tomorrow tries again. The refusals are
 * counted and warned on, and the exit code stays 0 — `PruneTenantExports`' rule,
 * and 1993's finding is why the warning is not optional: a silent refusal that
 * printed *"nothing to prune"* was affirmatively false about the one thing an
 * operator would have acted on.
 */
#[Signature('storage:prune')]
#[Description('Delete stored objects past the retention period stated for their kind')]
final class PruneStoredObjects extends Command
{
    public function handle(StorageRetention $retention): int
    {
        // Never inherit a tenant from whatever ran before this in the process.
        Tenancy::forgetAll();

        $kinds = $this->kindsWithAPeriod($retention);

        $this->reportUnsetPeriods($retention);
        $this->reportTheStoreThisSweepCannotReach();

        if ($kinds === []) {
            // ⛔ THE ORDINARY CASE TODAY, AND IT RETURNS BEFORE TOUCHING A
            // SINGLE ACCOUNT. Not merely "prunes nothing" — never opens the
            // enumeration at all, so there is no code path from an unset period
            // to a query, let alone to a delete.
            $this->info('No retention period is set for any kind, so nothing was deleted.');

            return self::SUCCESS;
        }

        /** @var array<string, array{int, int}> $tally kind value => [pruned, refused] */
        $tally = [];

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$tally, $kinds, $retention): void {
                foreach ($users as $user) {
                    $this->sweepOwner((int) $user->getKey(), $kinds, $retention, $tally);
                }
            });

        // The PostgreSQL session variable outlives this process's connection
        // under any pooler, and a worker inheriting it would start as whichever
        // tenant this loop happened to touch last.
        Tenancy::forgetAll();

        $this->report($kinds, $tally, $retention);

        return self::SUCCESS;
    }

    /**
     * The kinds an operator has stated a period for.
     *
     * @return list<StoredObjectKind>
     */
    private function kindsWithAPeriod(StorageRetention $retention): array
    {
        return array_values(array_filter(
            StoredObjectKind::cases(),
            static fn (StoredObjectKind $kind): bool => $retention->periodFor($kind) !== null,
        ));
    }

    /**
     * Every kind kept indefinitely, named with the key that would change it.
     *
     * ⚠️ **INCLUDING `Export`, WHICH IS NOT ONE OF THEM.** It has no key and
     * needs none — `exports:prune` deletes it at seven days, published — so
     * printing it here beside "kept for ever" would be false. It is named on its
     * own line instead, because an operator counting five kinds and reading four
     * would otherwise assume the fifth was missed.
     */
    private function reportUnsetPeriods(StorageRetention $retention): void
    {
        $unset = [];

        foreach (StoredObjectKind::cases() as $kind) {
            $key = $kind->retentionKey();

            if ($key === null) {
                continue;
            }

            if ($retention->periodFor($kind) === null) {
                $unset[] = '  '.str_pad($kind->label(), 22).'kept indefinitely — set '.$key;
            }
        }

        if ($unset === []) {
            return;
        }

        $this->newLine();
        $this->line('Kinds with no keeping period stated. Nothing of these is ever deleted:');

        // ⚠️ ONE `line()` PER KIND RATHER THAN ONE JOINED STRING. Laravel's
        // `expectsOutputToContain` consumes a whole recorded output entry per
        // assertion, so four kinds emitted as one entry can only ever be
        // asserted once — a test that then "passes" on three of the four names
        // it was given. The output reads identically; the assertion does not.
        foreach ($unset as $line) {
            $this->line($line);
        }

        $this->line('  '.str_pad(StoredObjectKind::Export->label(), 22)
            .'deleted after '.ExportBuilder::LINK_EXPIRY_DAYS
            .' days by exports:prune — published, and not an operator\'s to move.');
        $this->newLine();
    }

    /**
     * The store no period here reaches, named on every run.
     *
     * ⚠️ **THE SAME ARGUMENT AS THE `Export` LINE ABOVE, ONE STORE FURTHER
     * OUT.** {@see self::reportUnsetPeriods()} names `Export` even though it is
     * not one of the unset kinds, *"because an operator counting five kinds and
     * reading four would otherwise assume the fifth was missed"*. The pixel
     * event archive is a **sixth** store — written one gzipped object per beacon
     * per business — and it is not a {@see StoredObjectKind} at all, so it
     * appears on neither list and an operator reading this output has no way to
     * discover it exists. That is the omission being quiet, which
     * `StoredObjectKind`'s bolded rule says is worse than no metric.
     *
     * ⛔ **AND THE OMISSION IS PERMANENT ON PURPOSE, WHICH IS WHY THIS IS A LINE
     * AND NOT A CASE.** Giving the archive a case would give it a
     * `storage.retention_days.…` key, and an operator who set that key would be
     * either destroying the record of truth — L0 is the only copy, and every
     * range before the cut becomes permanently unreplayable — or setting a
     * period that deletes nothing. `StoredObjectKind`'s docblock carries all
     * three reasons; L0's period is an owner's ruling with a bucket lifecycle
     * rule behind it (7706).
     *
     * ⚠️ **UNCONDITIONAL, LIKE THE UNSET-KEY REPORT IS NOT.** That one returns
     * early when every period is set; this store has no period to set, so there
     * is no run on which the sentence stops being true.
     */
    private function reportTheStoreThisSweepCannotReach(): void
    {
        $this->newLine();
        $this->line('Pixel event archive (L0) is outside this sweep entirely. It is not a kind,');
        $this->line('  it has no keeping period to set, and nothing in this application deletes');
        $this->line('  it on any period — only a tenant erasure removes it (ObjectStoreL0Archive).');
        $this->newLine();
    }

    /**
     * Every business this user owns, through the `owner_lookup` policy.
     *
     * @param  list<StoredObjectKind>  $kinds
     * @param  array<string, array{int, int}>  $tally
     */
    private function sweepOwner(int $userId, array $kinds, StorageRetention $retention, array &$tally): void
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and no
        // tenant is established yet — the circularity `ResolveTenant` documents.
        // The database still restricts this to businesses owned by the user just
        // set, so it cannot widen beyond one person's own.
        $businessIds = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->pluck('id');

        foreach ($businessIds as $businessId) {
            foreach ($kinds as $kind) {
                $sweep = Tenancy::actingAs(
                    (int) $businessId,
                    fn (): StorageSweep => $retention->prune($kind),
                );

                $current = $tally[$kind->value] ?? [0, 0];

                $tally[$kind->value] = [
                    $current[0] + $sweep->pruned,
                    $current[1] + $sweep->refused,
                ];
            }
        }
    }

    /**
     * @param  list<StoredObjectKind>  $kinds
     * @param  array<string, array{int, int}>  $tally
     */
    private function report(array $kinds, array $tally, StorageRetention $retention): void
    {
        $refusedTotal = 0;

        foreach ($kinds as $kind) {
            [$pruned, $refused] = $tally[$kind->value] ?? [0, 0];
            $refusedTotal += $refused;

            $this->info(
                $kind->label().': '.$pruned.' deleted after '
                .$retention->periodFor($kind).' days.'
            );
        }

        if ($refusedTotal > 0) {
            // ⚠️ AFTER the lines above rather than instead of them, and both are
            // true: "nothing was deleted" and "something could not be deleted"
            // are separate facts, and collapsing them leaves a run that pruned
            // nine and failed on the tenth reporting only the nine (1993).
            $this->warn(
                $refusedTotal.' object'.($refusedTotal === 1 ? '' : 's')
                .' could not be removed — the object store refused, or the row named '
                .'no reachable object. The rows are kept so tomorrow\'s run tries again; '
                .'check the bucket credentials.'
            );
        }
    }
}
