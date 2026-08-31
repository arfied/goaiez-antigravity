<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BillingTerm;
use App\Models\Business;
use App\Models\User;
use App\Services\Billing\PlanCharges;
use App\Services\Billing\Subscriptions;
use App\Support\PlanPricing;
use App\Support\PlanSelection;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who is grandfathered, who is exposed — CC-7 §4's runnable check (3443, 3444).
 *
 * ⛔ **THE QUESTION NOTHING COULD ANSWER BEFORE THIS.** 3443 makes an existing
 * customer keep the price they signed up at for ever, and 3518–3547 built it as
 * four stored columns on `subscriptions` read back through
 * {@see PlanCharges::agreedPriceFor()}. A lint enumerates every surface that
 * must read the stored figure (`tests/Feature/Architecture/BillingTest.php`,
 * *"a surface quoting an existing subscription reads the agreed price"*), so the
 * **code** is held. What no one could ask was the **data** question: which live
 * subscriptions actually carry a stored price, and which fall back to today's
 * registry. Answering it needed `tinker` against production, which is 4319's
 * shape — a rule with no off switch you can run.
 *
 * ## ⚠️ IT IS NOT ONE QUERY, AND THE REASON IS RLS RATHER THAN TASTE
 *
 * CC-7 asks for *"the one-query grandfather check"*. `subscriptions` is FORCE
 * ROW LEVEL SECURITY on a policy keyed to the session tenant, so a single
 * cross-tenant `SELECT` returns **nothing** as the runtime role and dropping a
 * global scope does not help — the policy is in the database. Reaching each
 * business through its owner is the pattern its siblings already use; read
 * {@see SendRenewalReminders} and {@see AdvanceDunningSchedules} before changing
 * it. The alternative is connecting as the owning role, which is the one thing
 * that would make RLS decoration.
 *
 * ## ⚠️ WHAT "EXPOSED" MEANS, AND WHY IT IS NOT A DEFECT BY ITSELF
 *
 * A row whose four agreed-price columns are null is one sold before those
 * columns existed. `agreedPriceFor()` falls back to the registry for it, which
 * is the pre-existing behaviour rather than a new risk (3533) — but it is also
 * exactly the row that gets repriced the next time an operator edits a plan
 * price, and the tenant would never be told. **So it is reported by name and the
 * command exits non-zero**: it is somebody's to resolve, and a check that always
 * exits zero is one nobody reads.
 *
 * ⚠️ **`--tenant` NARROWS AND DOES NOT WIDEN.** With no argument this walks
 * every business through its owner; with one it establishes that tenant
 * directly. Both read exactly what a logged-in owner could read.
 */
#[Signature('billing:grandfather-check {--tenant= : Check one business id rather than every account}')]
#[Description('Report which subscriptions carry the price they were sold at, and which fall back to today\'s registry')]
final class CheckGrandfatheredPricing extends Command
{
    public function handle(PlanCharges $charges, Subscriptions $subscriptions): int
    {
        /** @var list<array{business: int, state: string, agreed: string, live: string}> $rows */
        $rows = [];

        $tenant = $this->option('tenant');

        if (is_string($tenant) && $tenant !== '') {
            $rows = Tenancy::actingAs((int) $tenant, function () use ($tenant, $charges, $subscriptions): array {
                $business = Business::query()->find((int) $tenant);

                return $business instanceof Business
                    ? $this->rowsFor($business, $charges, $subscriptions)
                    : [];
            });
        } else {
            User::query()
                ->select('id')
                ->orderBy('id')
                ->chunkById(200, function (Collection $users) use ($charges, $subscriptions, &$rows): void {
                    foreach ($users as $user) {
                        $rows = [...$rows, ...$this->rowsForOwner((int) $user->getKey(), $charges, $subscriptions)];
                    }
                });
        }

        // Never leave a security context established after a console command —
        // the PostgreSQL session variable outlives this process's connection
        // under any pooler, and a worker inheriting it would start as whichever
        // tenant the loop happened to touch last.
        Tenancy::forgetAll();

        if ($rows === []) {
            $this->info('No subscriptions to check.');

            return self::SUCCESS;
        }

        $this->table(['Business', 'State', 'Agreed', 'Quoted today'], $rows);

        $exposed = array_values(array_filter($rows, static fn (array $row): bool => $row['state'] === 'exposed'));

        if ($exposed !== []) {
            $this->error(count($exposed).' of '.count($rows).' subscriptions carry no agreed price and would be '
                .'quoted today\'s figure. Find out what each was sold at before the next price change (3443).');

            return self::FAILURE;
        }

        $this->info(count($rows).' subscriptions all carry the price they were sold at.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{business: int, state: string, agreed: string, live: string}>
     */
    private function rowsForOwner(int $userId, PlanCharges $charges, Subscriptions $subscriptions): array
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and no
        // tenant is established yet — the same circularity ResolveTenant
        // documents. The database still restricts this to businesses owned by
        // the user just set, so it cannot widen beyond one person's own.
        $businesses = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->get();

        $rows = [];

        foreach ($businesses as $business) {
            $rows = [
                ...$rows,
                ...Tenancy::actingAs(
                    $business->id,
                    fn (): array => $this->rowsFor($business, $charges, $subscriptions),
                ),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{business: int, state: string, agreed: string, live: string}>
     */
    private function rowsFor(Business $business, PlanCharges $charges, Subscriptions $subscriptions): array
    {
        // ⚠️ THROUGH THE BILLING SERVICE RATHER THAN THE MODEL, AND A LINT SAYS
        // SO. `Architecture/BillingTest`'s *"only the billing service writes a
        // subscription"* forbids `Subscription::` outside an enumerated list —
        // reads included, because the list is about who may name the table at
        // all. `Subscriptions::for()` is the read every other caller uses.
        $subscription = $subscriptions->for($business);

        if ($subscription === null) {
            return [];
        }

        $selection = $subscription->term === BillingTerm::Annual
            ? PlanSelection::annual($subscription->additional_locations ?? 0)
            : PlanSelection::monthly($subscription->additional_locations ?? 0);

        // ⚠️ BOTH FIGURES, SIDE BY SIDE, BECAUSE ONE OF THEM PROVES NOTHING.
        // No plan price has ever moved, so on today's data these two agree for
        // every account — printing only the agreed one would read as a working
        // check while saying nothing at all. The pair is what makes a divergence
        // visible the day an operator edits a price, and what makes a `—` in the
        // agreed column unmissable.
        $agreed = $subscription->price_cents === null
            ? '—'
            : PlanPricing::format($charges->agreedPriceFor($subscription));

        return [[
            'business' => $business->id,
            'state' => $subscription->price_cents === null ? 'exposed' : 'grandfathered',
            'agreed' => $agreed,
            'live' => PlanPricing::format($charges->priceFor($selection)),
        ]];
    }
}
