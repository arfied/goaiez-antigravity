<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\BenchmarkVertical;
use App\Models\Business;
use Illuminate\Support\Facades\DB;

/**
 * Delete one business's derived warehouse rows, and the cohort its numbers fed.
 *
 * ⛔ **LIFTED OUT OF `PhiExclusion` ON 2026-08-30 (12539), WHICH IS THE SECOND
 * HALF OF THE LIFT `DerivedTables` WAS THE FIRST HALF OF.** The owner overrode
 * `29` §2 rule 24 and its §12.1 line, retiring the PHI machinery — and
 * `PhiExclusion` held **three** unrelated things, not one: the derived-table
 * list ({@see DerivedTables}), a tenancy precondition that had nothing to do
 * with PHI ({@see L1Loader}), and this purge. **Only the refusals were rule
 * 24's.** Deleting the class whole would have taken all three.
 *
 * ⚠️ **THE PURGE IS NOT A PHI CONTROL AND WAS NEVER ONLY ONE.** Its subject is
 * *a business whose derived rows must go*, and the reason it is asked is the
 * caller's. `TenantClassification` asked it on a raise; the retention sweep
 * deletes the same rows on a period. **Keeping it named for the rule that has
 * just been retired is how a live mechanism gets deleted next year by somebody
 * tidying up.**
 *
 * ⛔ **THE L3 ARM DELETES BY `vertical`, NOT BY `business_id`, AND THAT IS NOT A
 * BUG.** `l3_benchmark_cohort_daily` carries no `business_id` — that is rule 1
 * of the layer — so the only way to remove one tenant's contribution is to drop
 * the cohort rows it fed and let the next derivation rebuild them without it.
 * ⚠️ **It therefore removes other tenants' contributions too, temporarily.**
 * That was true before this move and is unchanged by it; it is stated here
 * because the class name no longer implies a rare compliance event.
 *
 * ⚠️ **A BUSINESS WITH NO RECOGNISED `vertical` HAS NO COHORT TO DROP**, so the
 * L3 count stays zero rather than the sweep guessing at one.
 */
final class DerivedPurge
{
    /**
     * @return array{l1: int, l2: int, l3: int}
     */
    public function forBusiness(int $businessId): array
    {
        $deleted = ['l1' => 0, 'l2' => 0, 'l3' => 0];

        foreach (DerivedTables::DERIVED_TABLES as $layer => $tables) {
            foreach ($tables as $table) {
                $deleted[$layer] += DB::table($table)->where('business_id', $businessId)->delete();
            }
        }

        $vertical = BenchmarkVertical::tryFrom(
            (string) (Business::query()->whereKey($businessId)->value('vertical') ?? '')
        );

        if ($vertical !== null) {
            foreach (DerivedTables::NETWORK_TABLES['l3'] as $table) {
                $deleted['l3'] += DB::table($table)->where('vertical', $vertical->value)->delete();
            }
        }

        return $deleted;
    }
}
