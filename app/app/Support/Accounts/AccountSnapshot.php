<?php

declare(strict_types=1);

namespace App\Support\Accounts;

use App\Enums\CreditProduct;
use App\Enums\DataClassification;
use App\Models\Location;
use App\Models\OauthConnection;
use App\Models\Subscription;
use App\Models\WizardProgress;
use App\Support\Trials\TrialGrantVerdict;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Everything Account 360 v1 shows about one customer, read in one place.
 *
 * A value object rather than a bag of view variables, for the reason
 * `AdminDetail` gives about detail layouts: a screen that assembles its own
 * reads eventually reaches for one more, and the first model that reaches is
 * usually the one holding credentials. This names the fields, so widening what
 * staff can see about a tenant is an edit to a type somebody reviews rather
 * than a line in a template.
 *
 * ⚠️ NOTHING HERE IS A CUSTOMER'S CUSTOMER. The tenant's own contacts, reviews,
 * consent records and message bodies are all absent by construction, and the
 * per-location totals are counters the owner already sees on their own
 * dashboard — not rows. An agent who needs the underlying record opens a
 * support session, which is time-limited, recorded, and visible to the owner
 * (`28` §9.4). That is the difference between a console and a back door.
 *
 * Built only by [[\App\Services\Support\AccountDirectory]], inside that
 * business's own tenancy.
 */
final readonly class AccountSnapshot
{
    /**
     * @param  Collection<int, Location>  $locations
     * @param  Collection<int, OauthConnection>  $integrations
     * @param  array<string, array{monthly: int, top_up: int}>  $creditBalances
     *                                                                           Keyed by {@see CreditProduct}'s backing value.
     */
    public function __construct(
        public int $businessId,
        public string $name,
        public ?string $ownerName,
        public ?string $ownerEmail,
        public DataClassification $classification,
        public ?CarbonInterface $createdAt,
        public ?Subscription $subscription,
        public Collection $locations,
        public Collection $integrations,
        public ?WizardProgress $setup,
        public bool $baaInForce,
        /**
         * `28` §9.3's header flags, and the state its quick-actions rail acts
         * on (§9.5).
         *
         * ⚠️ **BOTH, RATHER THAN ONE "STOPPED" FLAG**, and the screen shows them
         * as two rows for the reason `Livewire\Setup\ReviewRules` shows two: a
         * tenant can be paused *and* suspended, they are cleared by different
         * people, and an agent who cannot tell them apart will lift a suspension
         * and tell the owner their account is running when it is not.
         *
         * ⚠️ **NEITHER IS READ OFF THE COLUMN.** `TenantPause` and
         * `TenantSuspension` are each held to their columns by a lint, and every
         * name on those allowlists is a file that can *write* one — so a screen
         * that only reads goes through the writer instead of onto the list.
         */
        public bool $paused,
        public ?string $pauseReason,
        public bool $suspended,
        /**
         * The internal note, shown here and **not** on the owner's status page.
         * The next agent to pick up this account needs the finding the last one
         * wrote; the customer gets a conversation with a human instead of
         * unreviewed prose. See `SuspendedAccountController`.
         */
        public ?string $suspensionReason,
        public ?string $suspendedBy,
        /**
         * All six balances (`App\Services\Billing\CreditLedger`), read the way
         * the ledger itself is authoritative: the head row's `balance_after` per
         * product and pool, never a sum. Zero for a tenant who has never had a
         * movement — a knowable fact, not a missing one.
         *
         * ⛔ **SIX FIGURES AND NO SEVENTH.** Three products × two pools (3419),
         * and **nothing adds any two of them** (3428): 500 sends plus 300,000
         * hundredths of a cent is a number with no meaning that renders
         * perfectly. This held one `int` — the SMS total — until 3437 item (3)
         * was closed, and the reason recorded for the other five being absent was
         * that nothing in the console could move them. `CreditGrants` now can, so
         * the operator who could previously only grant blind sees what they are
         * granting into.
         *
         * ⚠️ **THE UNITS ARE THE PRODUCTS' OWN** (`CreditProduct::unit()`): whole
         * sends for texts and emails, **hundredths of a cent** for AI. Turning
         * that into money belongs to whatever prints it, and there is exactly one
         * inverse to do it with (`CreditUnit::toCents()`).
         */
        public array $creditBalances,
        /**
         * Whether anything in the top-up pool can be spent today (3441, 3926).
         *
         * ⛔ **A SUPPORT GRANT LANDS IN THE POOL THIS GATES.** `CreditKind::Adjust`
         * funds `CreditPool::TopUp` — deliberately, so goodwill credit is still
         * there next month — and since 3441 that pool is filtered out of the draw
         * order while the plan is inactive. So an operator could add 750 emails to
         * a cancelled account, the ledger accepted it, the balance card showed it,
         * and **not one of them could be sent**. Nothing on this screen said so.
         *
         * ⚠️ **IT IS `Subscriptions::isEntitled()`, THE SAME ANSWER THE LEDGER'S
         * OWN GATE ASKS** (`CreditLedger::planPermitsThisMovement()`), so this screen
         * cannot promise a spend the ledger would refuse or stay quiet about one
         * it would. `Livewire\Account\Credit`'s `planIsRunning` is the
         * tenant-facing sibling of exactly this field.
         *
         * ⚠️ **IT IS NOT AN EXPIRY AND MUST NEVER BE RENDERED AS ONE.** The
         * balance is untouched and becomes spendable again the moment the plan
         * does, so the honest word is *waiting* (`CLAUDE.md`, 3441).
         */
        public bool $creditIsSpendable,
        /**
         * What the no-card trial's abuse controls find about this account
         * (2066, 3117).
         *
         * ⚠️ **SHOWN TO AN OPERATOR RATHER THAN ONLY THROWN AT A CALLER, AND
         * THAT IS WHAT KEEPS THE CONTROL FROM BEING DECISION 272's SHAPE.** The
         * verdict's intended reader is the grant lane, which does not exist —
         * `CreditKind::Grant` is constructed nowhere in `app/` and a lint fails
         * the build on anything that constructs it (3119). A control whose only
         * reader is unbuilt is a control nobody would notice had stopped
         * working. The operator on this screen is the *live* reader:
         * `Accounts::grantCredits()` is the one path in this application that
         * can move a tenant off a zero balance (3102), so the person about to
         * fund an account is shown what the automatic controls would say about
         * it.
         *
         * ⛔ **IT IS INFORMATION AND NOT A GATE.** Nothing here refuses the
         * operator's grant. A goodwill credit after an outage has nothing to do
         * with trial fraud, and two of the three refusals have entirely
         * legitimate causes — a business sold to a new owner, a shared office.
         * The screen tells them; they decide.
         */
        public TrialGrantVerdict $trialGrant,
        /**
         * When this account's no-card free trial runs out, or null if it is not
         * on one (9328, 9332).
         *
         * ⛔ **THE FIELD EXISTS BECAUSE THE SCREEN HAD NO WAY TO ANSWER THE
         * QUESTION SUPPORT IS ABOUT TO BE ASKED.** From 2026-08-25 a
         * `pending_checkout` trial is bounded at `billing.trial_days` from
         * registration, and the row this screen renders carries **no date at
         * all** for that population — `subscriptions.trial_ends_at` is written
         * only by the two card-bearing paths. So an operator taking *"my account
         * stopped working"* would have read `Pending checkout`, no trial date,
         * *"their plan is not running"*, and had nothing to say.
         *
         * ⚠️ **IT IS DERIVED, LIKE THE RULE ITSELF** — `Subscriptions::noCardTrialEndsAt()`
         * — so it cannot disagree with the gate that refuses the tenant. A second
         * stored date beside `trial_ends_at` is exactly what
         * `Subscriptions::openPendingSignup()` refuses.
         *
         * ⚠️ **NULL IS TWO DIFFERENT ACCOUNTS AND THE SCREEN MUST NOT COLLAPSE
         * THEM**: a tenant who is past Checkout has no no-card trial to end, and
         * so does one with no subscription row at all. Neither is on this clock.
         *
         * ⚠️ **REQUIRED RATHER THAN DEFAULTED**, though it is last and nullable
         * and a default would compile. A defaulted field is one a new caller
         * omits silently, and what it omits here is the only date this screen
         * can show for the population it most needs one for.
         */
        public ?CarbonInterface $noCardTrialEndsAt,
    ) {}
}
