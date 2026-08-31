<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Plan;
use App\Models\PlanEntitlement;
use App\Models\PlatformSetting;
use App\Services\Config\DefaultsRegistry;
use App\Support\DefaultsManifest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Loads `DefaultsManifest` into the two registry stores (doc `38` Part 2, CFG1).
 *
 * ## Why a command rather than a migration
 *
 * `38` Part 2 says the manifest is "loaded at migrate", and it is — this runs
 * from `DatabaseSeeder` and from `composer deploy`, immediately after the
 * migrations. It is not itself a migration, for three reasons that only show up
 * later:
 *
 *  - **A migration runs once.** Adding a key to the manifest afterwards would
 *    need a second migration, and then a third, and the seeds would be scattered
 *    across the migration history instead of sitting in one reviewed file. This
 *    command is idempotent, so a new key is one manifest edit.
 *  - **A rollback would delete values an operator set.** `down()` on a seeding
 *    migration either deletes rows it did not write or does nothing, and both
 *    are wrong.
 *  - **The seed has to be re-runnable against a live database.** That is the
 *    normal case on deploy, not the exception.
 *
 * ## What it will not do
 *
 * ⚠️ **IT NEVER OVERWRITES A VALUE THAT ALREADY EXISTS.** The registry's whole
 * premise is that these numbers are admin-editable; a sync that reset an
 * operator's edited budget on every deploy would make the Ops screen a lie, and
 * the failure would be silent — the budget would simply be back to 250 one
 * morning. Only missing keys are written. Putting a value *back* to its seed is
 * `DefaultsRegistry::resetToSeed()`, a deliberate act with an actor and a log
 * row.
 */
#[Signature('defaults:sync {--dry-run : Report what would be written without writing it}')]
#[Description('Seed any Defaults Registry values that are missing, never overwriting one that is set')]
final class SyncDefaultsRegistry extends Command
{
    /**
     * The actor recorded against a seeded row.
     *
     * A label rather than a user id, the same choice `audit_log.actor` makes:
     * nobody typed these values, the manifest did, and a row that claims a
     * person set it is worse than one that says where it came from.
     */
    public const string ACTOR = 'defaults:sync';

    public function handle(DefaultsRegistry $registry): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $settingsWritten = [];
        $entitlementsWritten = [];

        DB::transaction(function () use ($registry, $dryRun, &$settingsWritten, &$entitlementsWritten): void {
            foreach (DefaultsManifest::settings() as $key => $declared) {
                $existing = PlatformSetting::query()->find($key);

                if ($existing !== null) {
                    // Refresh the prose only. The description is documentation
                    // for whoever finds the row, so letting it go stale wastes
                    // the column — but it is not the value, so this writes no
                    // change log row and, by turning timestamps off for the
                    // save, does not bump `updated_at` either. That column means
                    // "when this value last moved", and a deploy that quietly
                    // restamped every row would make the Ops screen claim
                    // somebody had changed things.
                    if (! $dryRun && $existing->description !== $declared['description']) {
                        $existing->timestamps = false;
                        $existing->description = $declared['description'];
                        $existing->save();
                    }

                    continue;
                }

                $settingsWritten[] = $key;

                if (! $dryRun) {
                    $registry->set($key, $declared['seed'], self::ACTOR);
                }
            }

            foreach (DefaultsManifest::entitlements() as $planValue => $keys) {
                $plan = Plan::from($planValue);

                foreach ($keys as $key => $declared) {
                    if (PlanEntitlement::current($plan, $key) !== null) {
                        continue;
                    }

                    $entitlementsWritten[] = DefaultsRegistry::entitlementPath($plan, $key);

                    if (! $dryRun) {
                        $registry->setEntitlement($plan, $key, $declared['seed'], self::ACTOR);
                    }
                }
            }
        });

        $this->report('platform setting', $settingsWritten, $dryRun);
        $this->report('plan entitlement', $entitlementsWritten, $dryRun);

        // The withheld figures are printed on every run rather than only when
        // something is missing. They are the part of this file most likely to be
        // "fixed" by somebody who does not know they are deliberate, and an
        // operator reading a deploy log is exactly the person who might set one.
        foreach (DefaultsManifest::withheld() as $path => $reason) {
            $this->line("  <fg=yellow>withheld</> {$path} — {$reason}");
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $written
     */
    private function report(string $noun, array $written, bool $dryRun): void
    {
        $count = count($written);

        if ($count === 0) {
            $this->info("No missing {$noun} seeds.");

            return;
        }

        $verb = $dryRun ? 'Would seed' : 'Seeded';

        $this->info("{$verb} {$count} ".str($noun)->plural($count).':');

        foreach ($written as $key) {
            $this->line("  <fg=green>+</> {$key}");
        }
    }
}
