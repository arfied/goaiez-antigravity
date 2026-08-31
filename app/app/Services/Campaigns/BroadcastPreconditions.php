<?php

declare(strict_types=1);

namespace App\Services\Campaigns;

use App\Enums\CampaignKind;
use App\Enums\CreditPool;
use App\Enums\CreditProduct;
use App\Jobs\RunCampaignJob;
use App\Services\Billing\CreditLedger;
use App\Services\Messaging\SendingGuard;
use App\Services\Sms\BrandRegistrations;
use App\Services\Sms\TenantNumbers;
use App\Support\Tenancy;

/**
 * The three things that must be true before an SMS broadcast may leave —
 * decision 3310, asked **at send time and never at configure time**.
 *
 * The owner, verbatim: *"to use sms broadcasting not reactivation they need to
 * buy credit and finish 10dlc on there number they can not use a go ai ez number
 * for this they must have there own."*
 *
 * ## Why this is a containment rather than a form validation
 *
 * ⛔ **2101 IS THE REASON, AND 3310 IS THE ANSWER TO IT.** 2101 records the
 * structural cost of shared numbers with nobody able to fix it: attested lists go out over
 * the **GOAIEZ** 10DLC brand from **our own** number pool, so *"the tenant
 * carries the legal basis while the platform carries the carrier reputation"* —
 * across every tenant at once. A broadcast on the tenant's own brand and their
 * own number moves that exposure onto the party who attested to the list. So
 * these are not fields on a form that a screen can pre-flight; they are
 * properties of *the message about to be sent*, and the difference is what
 * decides whether the whole thing works:
 *
 *   - A filing can be **refused by the carriers at 3am** while a campaign that
 *     started at midnight is still running. Checked at configure time, every
 *     remaining message goes out on a brand nobody approves of any more.
 *   - A number can be **quarantined, parked or released** mid-run.
 *   - A purchased balance **runs out on the four-hundredth message**, which is
 *     the ordinary way a broadcast ends.
 *
 * That is {@see SendingGuard}'s own argument for living on the hot path, and
 * {@see RunCampaignJob}'s for consulting it between recipients rather than once
 * at enqueue.
 *
 * ## What this deliberately does not do
 *
 * ⚠️ **IT DOES NOT MAKE THE SEND LEAVE ON THAT NUMBER, AND THIS SLICE DOES NOT
 * CLOSE THAT** (3383). `NumberSelector` chooses the `from`, it is not this
 * lane's file, and it prefers a tenant-owned row over the shared pool by id
 * order. So the guarantee here is *"this tenant has their own brand and their
 * own sendable number, or nothing goes out"* — and after
 * {@see TenantNumbers::adoptOwnNumber()} the tenant holds exactly one number, so
 * the selector has nothing else to pick. **Written as what it is rather than
 * claimed as full routing enforcement**, because `CLAUDE.md`'s third recurring
 * failure is a protection layer asserted before it is true, and it has bitten
 * inside the slice that quotes it three times.
 *
 * ⚠️ **AND IT DOES NOT REPLACE ONE CONSENT RULE.** A broadcast is marketing
 * exactly as reactivation is (2100), so `ConsentService` is asked per recipient
 * with `OutreachPurpose::Marketing`, the registers and the recipient-local quiet
 * hours apply unchanged, and STOP, HELP and suppression are unconditional under
 * both bases (2099). 10DLC registration is carrier route approval and is **not**
 * recipient consent — carrier registration is distinct from recipient consent, and 2100 records it. What this adds
 * is *whose* infrastructure carries the traffic.
 */
final class BroadcastPreconditions
{
    public function __construct(
        private readonly BrandRegistrations $brands,
        private readonly TenantNumbers $numbers,
        private readonly CreditLedger $ledger,
    ) {}

    /**
     * Null means this campaign may send. Anything else is an operator's sentence
     * saying which of 3310's three preconditions is not met.
     *
     * ⚠️ **A SENTENCE RATHER THAN A `SendRefusalReason`, ON 2454's RULE.** All
     * three are states of the *tenant*, not facts about the contact being
     * messaged, and a `SendRefusalReason` written on a recipient row is a
     * statement about that person — so the runner marks these `Skipped` and the
     * reason reaches the log, exactly as it does for a pause, a suspension and
     * the global halt.
     *
     * ⚠️ **THE ORDER IS CHEAPEST-AND-MOST-DURABLE FIRST**, which is
     * `SendingGuard`'s ordering rule borrowed: the brand is one indexed
     * existence check and takes weeks to change, the number is one more, and the
     * balance is the one that moves every time a message goes out.
     *
     * ⛔ **AND EVERY BRANCH IS REACHED INDEPENDENTLY BY TEST** — 398's shape is
     * an outer guard refusing first, which would leave two of these three
     * unfalsifiable. Each is driven with the other two satisfied.
     *
     * ⚠️ **NONE OF THESE THREE SENTENCES IS TENANT-FACING AND NONE MAY BECOME
     * SO** (5329, 5422). They are operator sentences that reach a log, on
     * 2454's rule; 5329 nonetheless named them as *"what a tenant can currently
     * meet"*, and the fix was **not** to rewrite them. The tenant's half of the
     * first one is `App\Livewire\Account\Texting` — the same recorded
     * fact, in the tenant's own words, with the honest one-to-two-week wait that
     * these strings deliberately carry no room for. **The other two have no such
     * screen yet**: nothing tenant-facing says a tenant holds no number of their
     * own, and `Account\Credit` explains the purchased-balance rule (3309) but
     * never says a campaign is currently refused by it. Both are owed.
     */
    public function refusalFor(CampaignKind $kind): ?string
    {
        if (! $kind->requiresTenantOwnSendingIdentity()) {
            return null;
        }

        $businessId = Tenancy::idOrFail();

        if (! $this->brands->isApproved()) {
            return 'this account has no approved 10DLC registration of its own';
        }

        if ($this->numbers->ownBrandNumberFor($businessId) === null) {
            return 'this account has no number of its own that can send';
        }

        if ($this->purchasedBalance() <= 0) {
            return 'this account has no purchased message balance';
        }

        return null;
    }

    /**
     * The balance a broadcast is allowed to spend — **purchased credit only**.
     *
     * ⛔ **DECISION 3309 IS THE WHOLE OF THIS METHOD.** *"SMS broadcasting spends
     * top-up balance only … monthly sms credits can not be used for this."* The
     * monthly grant (500 SMS, 3298) pays for review invites, missed-call
     * text-back and the chat bot out of one shared balance (2066); a broadcast
     * pays for itself. Spending the grant on a blast is the one arithmetic
     * mistake here that the owner has explicitly forbidden.
     *
     * ⚠️ **IT ASKS FOR THE PURCHASED POOL BY NAME, AND IT DID NOT ALWAYS.** This
     * method was written against a `CreditLedger::balance()` that took no argument
     * and *was* the purchased pool — the class docblock said *"SCOPE IS THE
     * PURCHASED POOL … nothing here mints a plan allowance"* and
     * `CreditKind::Grant` was lint-forbidden, so with no grant ever minted the
     * head row's `balance_after` was purchased credit and nothing else.
     *
     * ✅ **THE TWO-POOL LEDGER LANDED IN THE SAME BATCH (3338–3357) AND THIS WAS
     * REPOINTED BEFORE ANYTHING SHIPPED.** `Grant` is now constructible and a
     * monthly allotment is minted, so the undifferentiated balance includes
     * credits 3309 forbids a broadcast from spending. The argument is passed
     * explicitly rather than relying on a default, because the safe reading must
     * be the one written down.
     *
     * ⛔ **THE OLD CALL WOULD HAVE FAILED OPEN, WHICH IS WHY THIS IS DRIVEN BY A
     * TEST AND NOT BY A COMMENT.** It would not have thrown or refused; it would
     * have read a larger number and let a broadcast start on the monthly
     * allotment, with the send succeeding and the ledger balancing — the only
     * wrong thing being which pool paid. `BroadcastPreconditionsTest`'s *"a
     * broadcast may not start on monthly credit alone"* funds the monthly pool,
     * leaves top-up at zero, and asserts the refusal; dropping the pool argument
     * from this line reddens it. **No reasoning about pools happens here** — a
     * second definition of the balance is `CreditLedger`'s own named hazard, and a
     * lint makes that class the only file in `app/` allowed to touch
     * `CreditLedgerEntry` at all.
     *
     * ⚠️ **AND IT NAMES THE PRODUCT SINCE 2026-08-14** (3419). The ledger now
     * holds three of them, so *"purchased balance"* had become ambiguous in
     * exactly the direction that costs money: the tenant's purchased **email**
     * units would have counted toward whether an SMS broadcast could start. The
     * product parameter has no default for that reason — the call would not
     * compile without an answer, so the question was asked rather than inherited.
     * Its sibling test *"a broadcast may not start on purchased email credit"*
     * drives it.
     *
     * ⛔ **AND IT ASKS THE SPENDABLE FIGURE RATHER THAN THE HELD ONE SINCE 3825**,
     * which is 3610's defect one guard over and the one place it had a live caller.
     * 3441 makes purchased credit unspendable while the plan is inactive, so
     * `balance(Sms, TopUp)` on a lapsed account returned the full amount, this
     * guard permitted the broadcast, and the ledger then refused **every single
     * send** — a campaign that starts, reports itself started, and delivers
     * nothing. The reader and the writer were each individually correct, which is
     * why nothing anywhere looked wrong.
     *
     * ⚠️ **THE REFUSAL WORDING IS UNCHANGED AND IS STILL HONEST.** *"No purchased
     * message balance"* is what the tenant has available to spend, which is what a
     * guard in front of a send means. 3441's *"your credits are waiting for you"*
     * is the account screen's job, not this string's — and this method must not
     * grow a second message, because the refusal a broadcast gives and the refusal
     * the ledger gives have to be the same answer.
     */
    private function purchasedBalance(): int
    {
        return $this->ledger->spendableBalance(CreditProduct::Sms, CreditPool::TopUp);
    }
}
