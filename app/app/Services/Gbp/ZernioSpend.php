<?php

declare(strict_types=1);

namespace App\Services\Gbp;

use App\Models\GbpAccountBinding;
use App\Models\WhatsappAccountBinding;
use App\Models\ZernioAccountDay;
use App\Services\Config\DefaultsRegistry;
use App\Services\Places\PlacesSpend;
use Illuminate\Support\Carbon;

/**
 * The meter and the brake on Google Business spend through Zernio.
 *
 * Decision 4685 found this the only paid vendor in `app/` reached with **no
 * meter, no budget, no ceiling and no debit**, and recommended a `ZernioSpend`
 * on {@see PlacesSpend}'s pattern. This is that class, and the two differ in one
 * structural way that has to be read before anything here makes sense.
 *
 * ---------------------------------------------------------------------------
 * WHAT ZERNIO ACTUALLY BILLS — verified against the live documents, 2026-08-17
 * ---------------------------------------------------------------------------
 *
 * ⛔ **A ZERNIO API CALL COSTS NOTHING, SO A PER-CALL LEDGER WOULD METER A
 * QUANTITY WHOSE PRICE IS ZERO.** From `docs.zernio.com/pricing`, read
 * 2026-08-17: *"Every account — free or paid — includes everything: full API
 * access, unlimited posts, all 14 platforms, Analytics, Inbox, and Ads. There
 * are no profile limits, no post caps, no add-ons, and no per-feature
 * pricing."* Copying `PlacesSpend`'s shape literally — a row per request, a
 * `unit_cents_per_thousand`, a daily cents ceiling — would have produced a
 * ledger whose every row cost 0¢ and a spend rate that is **permanently zero**,
 * which is `sending_health_windows` exactly (2496–2499): a set threshold on a
 * dead counter, with an isolation test passing perfectly against it.
 *
 * **The billable unit is a connected account, per day.** Same page:
 *
 *   Connected accounts   Price per account / month
 *   1–2                  Free, forever — no credit card required
 *   3–10                 $6 each
 *   11–100               $3 each
 *   101–2,000            $1 each
 *   2,001+               $1 each, no cap
 *
 * and `docs.zernio.com/billing` gives the mechanics: *"Every day, our system
 * records which accounts are connected and reports them to the billing engine,
 * one event per active account per day"*, then *"billable_units = (sum of all
 * account-days in the month) ÷ days_in_month"*, run through the ladder.
 *
 * ⚠️ **THE TWO PAGES STATE THE FREE TIER IN TWO INCOMPATIBLE-LOOKING FORMS AND
 * THE PLAUSIBLE READING IS THE WRONG ONE.** `/pricing` says the first two
 * accounts are free and the $6 band is *"3–10"* — eight accounts at $6.
 * `/billing` says the ladder is *"first 10 units at $6, next 90 at $3"* — ten
 * accounts at $6 — and then *"minus $12 free-tier credit"*. They agree because
 * $12 is 2 × $6: the free tier is implemented as **a monthly credit, not as a
 * tier exclusion**. Implemented from either page alone and checked against the
 * other's worked example, the figures differ by up to $12 a month, which is the
 * kind of quiet wrongness a plausible number produces. {@see costCentsForUnits()}
 * uses the credit form, and a test pins it against `/pricing`'s own worked
 * example — *"20 connected accounts costs $6 × 8 + $3 × 10 = $78/month"* — so
 * the two published forms have to keep agreeing or the build goes red.
 *
 * ⚠️ **NO PART OF THE GBP PATH TOUCHES THE PER-CALL SKUs.** Zernio does bill per
 * request for X/Twitter (`$0.005`–`$0.200`), per active managed ad, and for
 * phone numbers, calls and SMS. None of those is reachable from this
 * application: {@see ZernioGbpClient} calls `/inbox/reviews`, `/accounts`,
 * `/accounts/*`, `/profiles` and `/connect/googlebusiness` and nothing else.
 * (`GET /accounts` joined the list at 6603 — the connect callback's account
 * ownership check — and it is a *list* call rather than an account-scoped one,
 * which is why it is named separately from the glob above. It gained a **second
 * caller** at 6907, {@see ZernioReconciliation}, which asks it **unfiltered**;
 * 6614's reasoning covers both unchanged — one more request on a vendor that
 * does not bill per request.) Should an X
 * account or a Zernio number ever be connected, **this class does not meter it**
 * and a second meter is owed.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS IS A CEILING AND NOT A CREDIT-LEDGER DEBIT
 * ---------------------------------------------------------------------------
 *
 * 3297 says every path that spends money must debit the ledger. `credit_ledger`
 * denominates three products a tenant **buys** — SMS, email and AI — where a
 * debit is the tenant spending their own balance. Reading a tenant's own Google
 * reviews is not one of those: it is included in the plan, it is what the plan
 * is for, and a debit would mean the review engine stops for a tenant whose SMS
 * balance ran out. That is the hard-fail rule 43's surviving half forbids (3294)
 * and it would break the product at the exact moment a tenant most needs it.
 *
 * So the ceiling is **platform-scoped and in dollars**, like the three Places
 * budgets, and unlike `places.tenant_daily_spend_ceiling_cents` it is *not* a
 * per-tenant cap — 4688 raised that surviving per-tenant cap for a ruling and it
 * is deliberately untouched here. A per-tenant Zernio cap could not exist in any
 * useful form: one tenant's connected accounts are one or a few, always inside
 * any per-tenant figure, so the cap would be pure decoration on every account
 * while the platform total ran away unwatched.
 *
 * ---------------------------------------------------------------------------
 * WHAT HAPPENS WHEN THE CEILING IS REACHED, WHICH IS NOT AN ERROR
 * ---------------------------------------------------------------------------
 *
 * ⚠️ **REFUSING A TENANT'S GOOGLE CONNECTION WOULD BE A HARD FAIL, AND THIS
 * DELIBERATELY IS NOT ONE.** `29` §2 rule 44 requires every automation to
 * implement `execute()` **and** `handoff()`, and the whole review engine to run
 * with **zero** GBP API access. A tenant refused a connection here lands on
 * exactly the path a tenant with no Google access already lives on — reviews by
 * email ingest, replies as a one-tap Copy + Open Google handoff — which is a
 * first-class, fully built path in this product and not a degraded one. That is
 * what makes a ceiling on this path safe to have at all, and it is why
 * {@see GbpConnections::begin()} refuses with outcome language rather than an
 * error the owner cannot act on.
 *
 * FAILING CLOSED IS STILL THE DESIGN, on `PlacesSpend`'s terms: every read comes
 * from `DefaultsRegistry`, so a missing row, an unparseable value or a database
 * that cannot answer all mean *fewer* connections, never unlimited ones.
 */
final class ZernioSpend
{
    /**
     * `platform_settings` key. Namespaced by area, per that table's convention.
     */
    public const string CEILING_KEY = 'gbp.zernio_monthly_ceiling_cents';

    /**
     * The graduated ladder, in `/billing`'s form: units, then price per unit.
     *
     * Read as "the first 10 units cost 600¢ each, the next 90 cost 300¢ each,
     * everything above costs 100¢ each". The free tier is **not** in this table
     * — it is {@see FREE_TIER_CREDIT_CENTS} below, because that is how Zernio's
     * own billing engine applies it and the two forms only agree that way.
     *
     * ⚠️ **`null` is "no upper bound", not "unknown".** `/pricing` is explicit:
     * *"Past 2,000 accounts the rate simply stays at $1/account, self-service,
     * on the same plan."* The published table's 101–2,000 and 2,001+ bands carry
     * the same price, so they are one band here rather than two that can drift.
     *
     * @var list<array{units: ?int, cents: int}>
     */
    private const array LADDER = [
        ['units' => 10, 'cents' => 600],
        ['units' => 90, 'cents' => 300],
        ['units' => null, 'cents' => 100],
    ];

    /**
     * The free tier, as Zernio's billing engine applies it.
     *
     * `/billing`: *"account-days → divide by 30 → graduated tiers → minus $12
     * free-tier credit."* Two accounts at the $6 band. Subtracted after the
     * ladder and floored at zero, so one connected account is free rather than
     * minus six dollars.
     */
    public const int FREE_TIER_CREDIT_CENTS = 1200;

    public function __construct(
        private readonly DefaultsRegistry $registry = new DefaultsRegistry,
    ) {}

    public function freeTierCreditCents(): int
    {
        return $this->registry->int('gbp.zernio.free_tier_credit_cents');
    }

    /**
     * The graduated monthly cost of holding `$units` connected accounts.
     *
     * `$units` is fractional by construction — Zernio's own formula is
     * account-days ÷ days-in-month, so an account connected for half a month is
     * half a unit. Rounded once, at the end, to integer cents.
     *
     * ⚠️ **The ladder applies to the TOTAL, never to individual accounts**, and
     * `/billing` says so twice because it is the thing everybody gets wrong:
     * *"The graduated rate isn't tied to individual accounts — it applies to the
     * total billable units."* So there is no per-account rate to store on a row
     * and nothing here can be answered one account at a time.
     */
    public function costCentsForUnits(float $units): int
    {
        if ($units <= 0.0) {
            return 0;
        }

        $remaining = $units;
        $cents = 0.0;

        foreach (self::LADDER as $band) {
            $take = $band['units'] === null ? $remaining : min($remaining, (float) $band['units']);

            $cents += $take * $band['cents'];
            $remaining -= $take;

            if ($remaining <= 0.0) {
                break;
            }
        }

        return max(0, (int) round($cents - $this->freeTierCreditCents()));
    }

    /**
     * Connected Zernio accounts right now, platform-wide.
     *
     * Read from `gbp_account_bindings` and not from `gbp_connections`, and the
     * difference is not a preference. `gbp_connections` is RLS-`FORCE`d on
     * `business_id`, so a platform-wide count of it from outside any tenant
     * returns **zero** — a meter that reads it would report "we owe nothing"
     * forever, which is the most expensive shape a meter can take. The bindings
     * table is the platform index built for exactly this reason, written only by
     * {@see GbpConnections} and deleted by it on disconnect.
     *
     * ⛔ **AND IT IS KNOWABLY A LOWER BOUND ON WHAT ZERNIO CHARGES US — 6779(d),
     * MEASURED AT 6900.** An owner who grants `business.manage` at Zernio's
     * consent screen and never lands back on the redirect leaves a connected,
     * billable account with **no binding at all**, so this count cannot see it:
     * a money path with no counter does not look uncapped, it looks free (3297).
     * {@see ZernioReconciliation} is what asks the vendor directly and reports
     * the gap.
     *
     * ⛔ **THIS METHOD IS DELIBERATELY NOT CHANGED TO ASK THE VENDOR, AND THAT
     * IS A RULING RATHER THAN AN OMISSION** (6910). It runs inside
     * {@see GbpConnections::begin()} on an owner's own click, so a ceiling
     * reading a third party would refuse **every** connection on the platform
     * during Zernio's outage — the hard fail rule 43's surviving half forbids
     * (3294) — and it would put a network round trip in front of a button. The
     * two numbers are supposed to be able to disagree; the disagreement is the
     * finding.
     */
    public function connectedAccounts(): int
    {
        return GbpAccountBinding::query()->count() + WhatsappAccountBinding::query()->count();
    }

    /**
     * Write today's account-days — one row per connected account.
     *
     * ⚠️ **THIS IS THE WRITER, AND WITHOUT IT EVERYTHING ELSE HERE IS A
     * DECORATION** (272, and `sending_health_windows` at 2496–2499). Idempotent
     * through the unique index on (`account_ref`, `on_day`), so a re-run, a
     * retried cron or an operator invoking it by hand adds nothing: an
     * account-day counted twice overstates the bill by the amount that would
     * make somebody raise a ceiling that was never breached.
     *
     * @return int Rows written by this call — 0 on a second run the same day.
     */
    public function recordAccountDaysToday(?Carbon $day = null): int
    {
        $onDay = ($day ?? Carbon::now())->copy()->startOfDay();
        $written = 0;

        GbpAccountBinding::query()
            ->orderBy('id')
            ->chunkById(500, function ($bindings) use ($onDay, &$written): void {
                foreach ($bindings as $binding) {
                    $created = ZernioAccountDay::query()->firstOrCreate(
                        [
                            'account_ref' => $binding->account_ref,
                            'on_day' => $onDay->toDateString(),
                        ],
                        [
                            'business_id' => $binding->business_id,
                            'recorded_at' => Carbon::now(),
                        ],
                    );

                    if ($created->wasRecentlyCreated) {
                        $written++;
                    }
                }
            });

        return $written;
    }

    /**
     * Account-days accrued in the calendar month containing `$month`.
     */
    public function accountDaysInMonth(?Carbon $month = null): int
    {
        return ZernioAccountDay::query()
            ->inMonth($month ?? Carbon::now())
            ->count();
    }

    /**
     * Zernio's `billable_units` for the month so far.
     *
     * ⚠️ **DIVIDED BY THE DAYS IN THE MONTH, NOT BY THE DAYS ELAPSED**, which is
     * the vendor's formula and is the one that makes a mid-month reading mean
     * something: it rises monotonically and lands on the invoice figure at
     * month end. Dividing by days elapsed would give a *rate* rather than an
     * accrual, and on the 2nd of the month it would report a full month's bill.
     */
    public function billableUnits(?Carbon $month = null): float
    {
        $month ??= Carbon::now();

        return $this->accountDaysInMonth($month) / $month->daysInMonth;
    }

    /**
     * What this month has cost so far, in integer cents.
     *
     * The meter half of this class: evidence, reconcilable against Zernio's
     * itemised invoice, and not the thing that refuses anything.
     */
    public function accruedCentsThisMonth(?Carbon $month = null): int
    {
        return $this->costCentsForUnits($this->billableUnits($month));
    }

    /**
     * What `$accounts` connected accounts cost over a whole month.
     *
     * The brake half, and it is deliberately a *steady-state* figure rather than
     * an accrual. Three reasons, and the third is the one that matters:
     *
     *   - It is conservative. An account connected mid-month costs less than a
     *     full unit, so steady state is always at or above the real bill.
     *   - It does not depend on when in the month it is asked, so the answer to
     *     "may this tenant connect?" cannot flip on the 1st.
     *   - **It is the number an operator agreed to.** A ceiling read against an
     *     accrual would permit connecting a hundred accounts on the 28th — a
     *     rounding error this month and a bill nobody approved from the 1st.
     */
    public function steadyStateMonthCents(int $accounts): int
    {
        return $this->costCentsForUnits((float) max(0, $accounts));
    }

    /**
     * The platform's monthly Zernio ceiling in integer cents, failing closed.
     */
    public function monthlyCeilingCents(): int
    {
        return max(0, $this->registry->int(self::CEILING_KEY));
    }

    /**
     * Whether one more connected account stays inside the ceiling.
     *
     * ⚠️ **A `false` HERE IS NOT AN ERROR AND MUST NEVER BE RENDERED AS ONE** —
     * see the class docblock. It routes a tenant onto rule 44's `handoff()`
     * path, which this product guarantees works with zero Google access.
     *
     * A ceiling of zero refuses, on `PlacesSpend::allows()`'s rule: zero means
     * zero, and an unset or unreadable ceiling means fewer connections rather
     * than unlimited ones.
     */
    public function allowsNewAccount(): bool
    {
        $ceiling = $this->monthlyCeilingCents();

        if ($ceiling === 0) {
            return false;
        }

        return $this->steadyStateMonthCents($this->connectedAccounts() + 1) <= $ceiling;
    }

    /**
     * How much of the ceiling the current connection count would use, 0–100+.
     *
     * For the operator surface. Deliberately allowed to exceed 100: a ceiling
     * can be lowered under a live install base, and clamping the number would
     * hide the one state somebody needs to act on.
     */
    public function ceilingUsedPercent(): int
    {
        $ceiling = $this->monthlyCeilingCents();

        if ($ceiling === 0) {
            return 100;
        }

        return (int) round($this->steadyStateMonthCents($this->connectedAccounts()) / $ceiling * 100);
    }
}
