<?php

declare(strict_types=1);

namespace App\Services\Places;

use App\Enums\PlacesSku;
use App\Models\PlacesApiCall;
use App\Services\Config\DefaultsRegistry;
use App\Support\PlacesFieldTiers;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The meter and the brake on Google Places spend.
 *
 * `BUILD-PLAN` §6 names this risk in as many words: "a free, unauthenticated
 * endpoint spending metered Places calls … real money on someone else's meter",
 * with four controls, "all in the gate, none optional". This class is two of
 * them — the daily budget failing closed, and the metering that makes the budget
 * mean anything. The 24h cache is PlacesCache; the rate limit and Turnstile are
 * slice F.
 *
 * ---------------------------------------------------------------------------
 * WHAT 250 AUDITS/DAY ACTUALLY COSTS — the answer decision 193 asked for
 * ---------------------------------------------------------------------------
 *
 * Decision 193 set the ceiling at 250 audits/day and recorded, deliberately,
 * that "the dollar conversion is slice B's job — SKU prices verified against
 * live Google docs, never quoted from memory". Prices read 2026-07-31 and
 * **re-read against Google's live pages on 2026-08-29**; the field-mask tiers
 * that pick the SKU are now a transcribed table with its own fetch date
 * ({@see PlacesFieldTiers}) rather than a docblock. See PlacesSku.
 *
 * ⚠️ **THE 2026-08-29 RE-READ LEFT ALL THREE FIGURES BELOW UNCHANGED**, so the
 * arithmetic in this block is not stale — but it found a fourth SKU that was
 * wrong, and `skusPerAudit()` is now checked against the client's own field
 * masks on every run ("the audit bills the SKUs its own field masks buy"). A
 * mask that gains a dearer field reddens the build instead of quietly moving
 * the derived ceiling below.
 *
 * One audit, at the field masks `29` §6.2's four checks actually require:
 *
 *   Text Search Pro          resolve a typed name, show name + address   3.20c
 *   Place Details            hours, categories, photos, description,
 *   Enterprise + Atmosphere  rating, review count, and `reviews` —
 *                            without which reply-rate cannot exist       2.50c
 *   Nearby Search Enterprise 3 same-category competitors, with ratings   3.50c
 *                                                                       ------
 *                                                            per audit   9.20c
 *
 * At the 193 ceiling that is **$23.00/day** at list price. Over a 30-day month
 * — 7,500 audits — with each SKU's free allowance deducted once:
 *
 *   Text Search Pro           7,500 - 5,000 free = 2,500 x 3.20c    $80.00
 *   Details Ent + Atmosphere  7,500 - 1,000 free = 6,500 x 2.50c   $162.50
 *   Nearby Search Enterprise  7,500 - 1,000 free = 6,500 x 3.50c   $227.50
 *                                                                 -------
 *                                                       per month  $470.00
 *
 * The allowances are ~1–2 days of ceiling traffic for the Enterprise SKUs, so
 * they shave the first days of a month rather than forming any part of a plan.
 *
 * CORRECTED 2026-07-31 (decision 253). This block previously read 4.00c for
 * Details, 10.70c per audit and ~$570/month, because the Atmosphere price was
 * taken from the Text Search row of the same table. See PlacesSku.
 *
 * THIS NUMBER WAS NOT VISIBLE WHEN 193 WAS TAKEN, and it is an owner decision,
 * not ours. So nothing here changes 250 — the budget still seeds at 250 and
 * still lives in `platform_settings` so it moves without a deploy. What this
 * class adds is that the spend is metered, bounded in dollars as well as in
 * audits, and visible. **The owner accepted this spend in decision 224**, at
 * the overstated ~$570 rather than the true ~$470, so the acceptance holds with
 * room to spare.
 *
 * Two things that make the real bill lower than the ceiling, neither of which
 * should be planned around: the 24h `place_id` cache means repeat lookups cost
 * nothing, and a budget is a ceiling rather than a forecast.
 *
 * One thing that would make it higher, and is therefore forbidden here:
 * fetching `reviews` for the three competitors as well as the subject would add
 * three Atmosphere calls per audit — 22,500 a month, roughly **$563/month on
 * its own**, more than the rest of the audit combined. Decision
 * 196 already keeps competitors aggregate and unnamed; competitor reply-rate is
 * dropped for cost, and the aggregate rating that Nearby Search already returns
 * carries the finding.
 *
 * ---------------------------------------------------------------------------
 *
 * FAILING CLOSED IS THE WHOLE DESIGN. Every read supplies its own conservative
 * default, so a missing settings row, an unparseable value, or a database that
 * cannot answer all mean *less* spend, never unlimited spend. DefaultsRegistry
 * enforces that by owning the fallbacks — this class no longer carries its own,
 * and cannot ask for a key the seed manifest has never declared.
 */
final class PlacesSpend
{
    /**
     * `platform_settings` keys. Namespaced by area, per that table's convention.
     */
    public const string BUDGET_KEY = 'public_audit.daily_budget';

    public const string SPEND_CEILING_KEY = 'public_audit.daily_spend_ceiling_cents';

    public const string AUTOCOMPLETE_BUDGET_KEY = 'public_audit.autocomplete_daily_budget';

    public const string TENANT_CEILING_KEY = 'places.tenant_daily_spend_ceiling_cents';

    /**
     * The `purpose` values on the ledger, which are also the budget boundaries.
     *
     * THREE BUDGETS, AND THE SEPARATION IS THE POINT. Autocomplete, the free
     * audit, and a paying tenant's own background work all bill Google through
     * this class, but they answer to different ceilings and none can consume
     * another's. On a single shared ceiling the failure is silent and backwards:
     * a day of heavy typing on the marketing home exhausts the budget, and the
     * audits — the thing the typing exists to produce — start failing closed for
     * visitors who did submit.
     *
     * The cheap convenience must never be able to starve the expensive product.
     *
     * ⚠️ **TENANT_PURPOSE WAS ADDED AFTER THE NIGHTLY COMPETITOR SYNC LANDED ON
     * THE AUDIT BUDGET.** That sync bills Details + Nearby — 6.00c — per
     * location per night, against a $23.00/day ceiling derived from decision
     * 193's 250 *visitor* audits. Roughly 380 synced locations exhausted the day
     * before any prospect arrived, and every free audit then failed closed: the
     * exact starvation this constant list exists to prevent, arriving from a
     * third direction nobody had drawn yet. Tenant work is neither free nor
     * unauthenticated, so it gets its own ceiling and its own `business_id` on
     * the ledger.
     */
    public const string AUDIT_PURPOSE = 'public_audit';

    public const string AUTOCOMPLETE_PURPOSE = 'public_audit_autocomplete';

    /**
     * Places calls made *for a tenant*, inside that tenant's session.
     *
     * Its ceiling is **per tenant per day**, not platform-wide, which the other
     * two are. A platform-wide tenant ceiling would only move the starvation
     * one level down — one busy account exhausting every other account's
     * competitor refresh — and `29` §2 rule 43 wants the cap per tenant anyway.
     */
    public const string TENANT_PURPOSE = 'tenant_places';

    /**
     * Calls made with the TENANT'S OWN Google key (X-206 service `google_places`).
     * Google bills their project, not ours, so no platform ceiling applies and
     * no platform sum may include them. Recorded so "what did the tenant's key
     * do today" is a query rather than a belief.
     */
    public const string OWN_KEY_PURPOSE = 'tenant_own_key';

    /**
     * ⚠️ **THE TWO DEFAULT BUDGETS USED TO BE CONSTANTS HERE. THEY ARE SEEDS IN
     * `DefaultsManifest` NOW, WITH THEIR REASONING**, and this class reads them
     * through `DefaultsRegistry` (doc `38` Part 2 / CFG1, decision 505). A
     * literal at a call site is precisely what that registry's lint refuses, and
     * the reason is not tidiness: two call sites reading the same key with
     * different in-code fallbacks disagree about policy, and whichever runs
     * first wins silently.
     *
     * The fail-closed property below is unchanged and is stricter. The manifest
     * seed is the conservative default, written once and reviewed; and a key the
     * manifest has never heard of now raises rather than resolving to whatever a
     * caller guessed.
     */
    public function __construct(
        private readonly DefaultsRegistry $registry = new DefaultsRegistry,
    ) {}

    /**
     * The SKUs one audit bills, in the order it bills them.
     *
     * Declared rather than observed so the cost of an audit is a fact about the
     * design, checkable by a test, instead of an emergent property of whatever
     * field masks happened to get written.
     *
     * @return list<PlacesSku>
     */
    public static function skusPerAudit(): array
    {
        return [
            PlacesSku::TextSearchPro,
            PlacesSku::PlaceDetailsAtmosphere,
            PlacesSku::NearbySearchEnterprise,
        ];
    }

    /**
     * List-price cost of one complete audit, in hundredths of a cent.
     *
     * Hundredths, because 3.20c + 2.50c + 3.50c is 9.2 cents — not a whole
     * number of cents at all. Integer cents would floor it to 9 and understate
     * every projection built on it by 2%.
     */
    public static function centsPerAuditX100(): int
    {
        return array_sum(array_map(
            static fn (PlacesSku $sku): int => $sku->centsPerThousand() / 10,
            self::skusPerAudit(),
        ));
    }

    /**
     * The daily audit ceiling, failing closed to decision 193's figure.
     */
    public function dailyAuditBudget(): int
    {
        $budget = $this->registry->int(self::BUDGET_KEY);

        // A negative or zero override is a mistake, not an instruction to stop
        // serving — but it is also not something to silently widen. Zero means
        // zero; negatives clamp to zero rather than wrapping into a huge budget.
        return max(0, $budget);
    }

    /**
     * The daily spend ceiling in integer cents, derived from the audit budget.
     *
     * Derived rather than configured, because an audit budget and a dollar
     * ceiling that disagree is a trap: whichever is looser is the real policy,
     * and nobody would know which. The owner sets audits; the money follows from
     * what an audit costs. An explicit override exists for the day a field mask
     * changes and the derivation stops matching reality.
     *
     * This is the second brake, and it exists because the first one does not
     * actually bound spend: one audit is three calls at three prices, and a
     * retry, a resolver miss, or a future fourth check all spend money without
     * incrementing the audit count.
     */
    public function dailySpendCeilingCents(): int
    {
        $derived = (int) ceil($this->dailyAuditBudget() * self::centsPerAuditX100() / 100);

        // intOr() rather than int(): this key's correct default is computed
        // rather than written down, so DefaultsManifest declares it without a
        // seed and says why. The manifest still has to name the key, so this is
        // not a side door back to in-code literals.
        return max(0, $this->registry->intOr(self::SPEND_CEILING_KEY, $derived));
    }

    /**
     * The daily autocomplete ceiling, in requests, failing closed.
     */
    public function dailyAutocompleteBudget(): int
    {
        return max(0, $this->registry->int(self::AUTOCOMPLETE_BUDGET_KEY));
    }

    /**
     * One tenant's daily Places ceiling, in integer cents, failing closed.
     *
     * In cents rather than in calls because tenant work is several SKUs at
     * several prices — the same reason `dailySpendCeilingCents()` exists next to
     * an audit count. A ceiling in calls would say nothing about the bill.
     */
    public function dailyTenantSpendCeilingCents(): int
    {
        return max(0, $this->registry->int(self::TENANT_CEILING_KEY));
    }

    /**
     * Cents spent on billable Places calls today.
     *
     * $purpose scopes the sum to one budget. Passing null sums everything, which
     * is what the ops view wants and what no budget check should ever use — a
     * ceiling checked against another purpose's spend is the starvation this
     * class exists to prevent.
     */
    public function spentTodayCents(?string $purpose = null): float
    {
        $unitSum = (int) PlacesApiCall::query()
            ->billable()
            ->onDay(Carbon::now())
            ->when($purpose !== null, fn ($query) => $query->where('purpose', $purpose))
            ->when($purpose === null, fn ($query) => $query->where('purpose', '!=', self::OWN_KEY_PURPOSE))
            ->sum('unit_cents_per_thousand');

        return $unitSum / 1000;
    }

    /**
     * Cents one tenant spent on billable Places calls today.
     *
     * Deliberately unscoped by tenancy — `places_api_calls` is a platform ledger
     * with no global scope, and this reads it with an explicit `business_id`
     * predicate. That predicate is the isolation here, so it is required rather
     * than optional: a caller that cannot name a tenant must not get a number.
     */
    public function spentTodayCentsForBusiness(int $businessId, string $purpose = self::TENANT_PURPOSE): float
    {
        $unitSum = (int) PlacesApiCall::query()
            ->billable()
            ->onDay(Carbon::now())
            ->where('business_id', $businessId)
            ->where('purpose', $purpose)
            ->sum('unit_cents_per_thousand');

        return $unitSum / 1000;
    }

    /**
     * Billable autocomplete requests made today.
     *
     * Counted rather than costed because the budget is expressed in requests:
     * autocomplete is one SKU at one price, so requests and money are the same
     * statement, and requests are the one an operator can reason about.
     */
    public function autocompleteRequestsToday(): int
    {
        return PlacesApiCall::query()
            ->billable()
            ->onDay(Carbon::now())
            ->where('purpose', self::AUTOCOMPLETE_PURPOSE)
            ->count();
    }

    /**
     * Audits started today, counted from the calls they made.
     *
     * Counted on the Text Search SKU rather than on `public_audits` rows,
     * because an audit that resolved from cache spent nothing and should not
     * consume budget — the budget bounds *spend*, and a free audit is free.
     */
    public function auditsToday(): int
    {
        return PlacesApiCall::query()
            ->billable()
            ->onDay(Carbon::now())
            ->where('purpose', 'public_audit')
            ->whereIn('sku', [PlacesSku::TextSearchPro, PlacesSku::TextSearchEnterprise])
            ->count();
    }

    /**
     * Whether one more billable call at this SKU is within today's budget.
     *
     * $purpose selects *which* budget, and the two are sealed off from each
     * other — see AUDIT_PURPOSE. It defaults to the audit so that every call
     * site written before autocomplete existed keeps its original meaning.
     *
     * Checks the money first on the audit path: it is the binding constraint and
     * the one that catches spend the audit counter cannot see.
     *
     * $businessId is required by TENANT_PURPOSE and ignored by the other two.
     * Tenant work with no tenant to bill is refused rather than waved through:
     * an unattributable call is exactly the one no ceiling can ever bound, and
     * failing closed here costs one background refresh.
     */
    public function allows(PlacesSku $sku, string $purpose = self::AUDIT_PURPOSE, ?int $businessId = null): bool
    {
        if ($sku->isFree()) {
            return true;
        }

        if ($purpose === self::OWN_KEY_PURPOSE) {
            return true;
        }

        if ($purpose === self::AUTOCOMPLETE_PURPOSE) {
            return $this->autocompleteRequestsToday() < $this->dailyAutocompleteBudget();
        }

        if ($purpose === self::TENANT_PURPOSE) {
            if ($businessId === null) {
                return false;
            }

            $tenantCeiling = $this->dailyTenantSpendCeilingCents();

            if ($tenantCeiling === 0) {
                return false;
            }

            return $this->spentTodayCentsForBusiness($businessId) + ($sku->centsPerThousand() / 1000)
                <= $tenantCeiling;
        }

        $ceiling = $this->dailySpendCeilingCents();

        if ($ceiling === 0) {
            return false;
        }

        // Scoped to the audit's own spend. Unscoped, autocomplete's cents would
        // count against the audit ceiling and a busy typing day would refuse
        // audits that are inside their own budget.
        if ($this->spentTodayCents(self::AUDIT_PURPOSE) + ($sku->centsPerThousand() / 1000) > $ceiling) {
            return false;
        }

        return $this->auditsToday() < $this->dailyAuditBudget();
    }

    /**
     * Record a call against the meter.
     *
     * Called for cache hits too, with $servedFromCache true, so that "the cache
     * is working" is a query rather than a belief.
     */
    public function record(
        PlacesSku $sku,
        string $purpose,
        ?string $placeId = null,
        bool $servedFromCache = false,
        ?int $businessId = null,
    ): PlacesApiCall {
        return PlacesApiCall::query()->create([
            'sku' => $sku,
            'place_id' => $placeId,
            'served_from_cache' => $servedFromCache,
            // A cache hit costs nothing, and the ledger says so in the money
            // column rather than only in a boolean — so any sum over the table
            // is correct without remembering to filter.
            'unit_cents_per_thousand' => $servedFromCache ? 0 : $sku->centsPerThousand(),
            'business_id' => $businessId,
            'purpose' => $purpose,
            'called_at' => Carbon::now(),
        ]);
    }

    /**
     * Today's spend broken down by SKU, for the ops view and for answering
     * "what did we spend it on?" after a bad day.
     *
     * @return array<string, array{calls: int, cents: float}>
     */
    public function todayBySku(): array
    {
        // Deliberately the query builder rather than Eloquent: the model casts
        // `sku` to a PlacesSku, and an enum cannot key an array. Casting it back
        // to ->value would work and would also mean every aggregate here pays
        // for hydration it does not use.
        /** @var array<int, object{sku: string, calls: int, unit_sum: string|int}> $rows */
        $rows = DB::table('places_api_calls')
            ->where('served_from_cache', false)
            ->where('purpose', '!=', self::OWN_KEY_PURPOSE)
            ->where('unit_cents_per_thousand', '>', 0)
            ->whereBetween('called_at', [
                Carbon::now()->startOfDay(),
                Carbon::now()->endOfDay(),
            ])
            ->groupBy('sku')
            ->select('sku', DB::raw('count(*) as calls'), DB::raw('sum(unit_cents_per_thousand) as unit_sum'))
            ->get()
            ->all();

        $out = [];

        foreach ($rows as $row) {
            $out[$row->sku] = [
                'calls' => (int) $row->calls,
                'cents' => (int) $row->unit_sum / 1000,
            ];
        }

        return $out;
    }
}
