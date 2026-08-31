<?php

declare(strict_types=1);

namespace App\Services\Gbp;

/**
 * What Zernio says is connected, against what this application thinks it is.
 *
 * ## Which side is right, and how somebody would tell
 *
 * ⚠️ **THE TWO SIDES ARE AUTHORITATIVE ABOUT DIFFERENT THINGS, AND NEITHER ONE
 * WINS OUTRIGHT.** {@see $billedAccounts} comes from the vendor's own account
 * list, and 4720 read the mechanism off their `/billing` page: *"Every day, our
 * system records which accounts are connected and reports them to the billing
 * engine, one event per active account per day."* **The list is the billing
 * basis, so on any disagreement about money, the vendor is right by
 * construction** — the invoice is computed from that list and not from ours.
 * {@see $boundAccounts} comes from `gbp_account_bindings`, which is the only
 * record anywhere naming a **location**, and the vendor's list cannot answer
 * that question at all (6764). **So on any disagreement about whose listing an
 * account is, our binding is right and theirs is silent.**
 *
 * ⛔ **AND THIS IS A COUNT AT AN INSTANT WHERE THE INVOICE IS AN INTEGRAL OVER A
 * MONTH.** Their formula is `billable_units = (sum of account-days) ÷
 * days_in_month`, so an account connected on the 3rd and disconnected on the
 * 9th is on the invoice and on neither side of this report. A month with churn
 * therefore **cannot** be reconciled from here, and the number that can be is
 * `zernio_account_days` — our own integral, written daily by `zernio:meter`
 * from the same bindings counted here. The honest reading of a disagreement is
 * *"these accounts are connected and unaccounted for today"*, which is the
 * question this report exists to answer, and never *"the invoice is wrong by
 * this much"*.
 *
 * ⚠️ **WHICH MEANS A CLEAN REPORT DOES NOT CLEAR THE MONTH.** An abandoned flow
 * that somebody has already disconnected at Zernio's console leaves nothing
 * here and days on the bill.
 */
final readonly class ZernioReconciliationReport
{
    /**
     * @param  list<ZernioOrphanedAccount>  $orphans
     * @param  list<int>  $businessesBoundButNotConnected
     */
    public function __construct(
        /**
         * Accounts the vendor reports connected under our platform key — the
         * number the ladder is applied to, including any it named unreadably.
         */
        public int $billedAccounts,
        /**
         * Rows in `gbp_account_bindings` — what `ZernioSpend` counts and meters.
         */
        public int $boundAccounts,
        /**
         * Entries in the vendor's list carrying no readable account id.
         *
         * ⚠️ Counted in {@see $billedAccounts} and absent from
         * {@see $orphans}: it is billed, and there is nothing to say about it
         * beyond that it exists. Rendered so the two totals visibly fail to add
         * up rather than silently doing so.
         */
        public int $unreadable,
        /**
         * Connected at the vendor, with no binding here.
         */
        public array $orphans,
        /**
         * Bound here, and absent from the vendor's list.
         *
         * ⚠️ **THE OTHER DIRECTION, AND IT IS NOT HARMLESS.** `zernio:meter`
         * writes an account-day for every binding (4721), so a binding the
         * vendor no longer lists makes our accrual **overstate** the bill and
         * makes `ZernioSpend::allowsNewAccount()` refuse connections the
         * platform could afford — routing owners onto the handoff path for a
         * ceiling nothing is actually spending against. Business references
         * only, on 4884's rule.
         */
        public array $businessesBoundButNotConnected,
        /**
         * What a full month at the vendor's count costs, in integer cents.
         */
        public int $billedMonthCents,
        /**
         * What a full month at our binding count costs, in integer cents.
         */
        public int $accountedMonthCents,
    ) {}

    public function orphanCount(): int
    {
        return count($this->orphans);
    }

    /**
     * The monthly cost of the accounts nothing here is using, in cents.
     *
     * ⚠️ **A DIFFERENCE OF TWO LADDER READINGS, NOT A PER-ACCOUNT RATE.**
     * `/billing` says it twice because everybody gets it wrong: *"The graduated
     * rate isn't tied to individual accounts — it applies to the total billable
     * units."* So there is no price for one orphan; there is only what the
     * platform's bill would be without them. Floored at zero because the free
     * tier is a credit rather than a band, so a small enough platform pays
     * nothing for its orphans and should be told that rather than shown a
     * negative.
     */
    public function orphanMonthCents(): int
    {
        return max(0, $this->billedMonthCents - $this->accountedMonthCents);
    }
}
