<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\CreditPool;
use App\Enums\CreditProduct;
use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WizardProgress;
use App\Services\Billing\CreditLedger;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Http\Request;

/**
 * `GET /api/me` — user + business + plan + wizard state (FOUND-04).
 *
 * The first thing any client calls, so it is also the first place the tenant
 * boundary has to hold on the API path.
 *
 * WHY IT READS THE TENANT FROM CONTEXT RATHER THAN FROM THE USER. The business
 * is whatever ResolveTenant established for this request, not whatever this
 * user's row points at. Those are the same value, and asking Tenancy is what
 * keeps them the same value: every scoped query in this request will use the
 * context tenant, so a resource assembled from a *different* source would be the
 * one place the two could drift — and it would drift silently, showing one
 * business's name above another business's numbers.
 *
 * With no tenant established — a user mid-signup, before their business exists —
 * every tenant-owned lookup here is skipped rather than attempted. Attempting
 * one would throw TenantNotResolved, which is the correct failure for data
 * access and the wrong response for "tell me about myself".
 *
 * ⚠️ **AND IT REPORTS ALL SIX CREDIT BALANCES, SEPARATELY** (3315, 3419, and
 * item (3) of 3437, which recorded that this endpoint answered about SMS alone
 * while the other four balances existed and were on no surface at all). Three
 * products × two pools, each read on its own — see {@see self::balances()},
 * which is the method decision 3422's defect was made in.
 */
final class MeController extends Controller
{
    public function __construct(
        private readonly CreditLedger $credits,
        private readonly DefaultsRegistry $defaults,
    ) {}

    public function __invoke(Request $request): MeResource
    {
        /** @var User $user */
        $user = $request->user();

        $businessId = Tenancy::id();

        if ($businessId === null) {
            return new MeResource($user, null, null, null);
        }

        return new MeResource(
            $user,
            Business::find($businessId),
            // ⚠️ AN UNORDERED `first()`, AND IT IS DETERMINISTIC BECAUSE THE
            // SCHEMA SAYS SO RATHER THAN BECAUSE THIS LINE DOES (3946).
            // `subscriptions.business_id` is `unique()` —
            // `2026_07_30_080943_create_subscriptions_table.php` — so a tenant has
            // at most one row and there is nothing for an ordering to choose
            // between. **Adding `latest('id')` here was tried and reverted**: it
            // would read as a claim that a business can hold several, which is the
            // opposite of what the schema allows, and 289's hazard needs more than
            // one row to bite. `ApiAuthenticationTest`'s *"a business cannot hold
            // a second subscription"* pins the constraint, so dropping it reddens
            // the build rather than leaving this line quietly wrong.
            Subscription::query()->first(),
            WizardProgress::query()->where('user_id', $user->id)->first(),
            $this->balances(),
            $this->creditCurrency(),
        );
    }

    /**
     * The currency the money-denominated balances are in (decision 3944).
     *
     * ⛔ **`billing.currency`, NOT `businesses.currency`, AND THEY ARE NOT THE
     * SAME QUESTION.** The AI pool holds *our* retail credit, priced by
     * `credits.monthly_grant.ai_cents` and the top-up SKUs — platform figures —
     * and `billing.currency` is the key every other money surface already reads:
     * `PlanPricing`, `TopUpCatalog`, `EmailCredits`, `AuthorizeNetWebhooks` and
     * `Livewire\Account\Credit`. `business.currency` is published on this payload
     * three blocks up and is a different fact; taking it here would be the copy
     * that disagrees the day 2058's multi-currency lands.
     *
     * ⚠️ **`PlanPricing`'s FALLBACK RATHER THAN `TopUpCatalog`'s REFUSAL**, which
     * is `Livewire\Account\Credit`'s choice made again for the same reason: that
     * class refuses without a currency because it is about to charge a card, and
     * this one is reporting a balance. An API that 500s because a platform
     * setting is unset tells a client nothing about their own money, and 2904
     * requires an exhausted or unknowable figure to degrade rather than throw.
     */
    private function creditCurrency(): string
    {
        $stored = $this->defaults->stringOrNull('billing.currency');

        return $stored === null || trim($stored) === '' ? 'USD' : strtoupper(trim($stored));
    }

    /**
     * All six balances — three products × two pools — each read on its own.
     *
     * ⛔ **SIX SEPARATE READS AND NOT ONE COMBINED ONE, WHICH IS 3315's RULING
     * SPELLED AS AN IMPLEMENTATION** (*"not one number but two balances"*). Every
     * figure here is `CreditLedger::balance()` asked for one product and one named
     * pool, so nothing in this file adds two of them together and nothing can.
     *
     * ⚠️ **THIS METHOD IS WHERE 3422 HAPPENED AND IT IS WHY THE POOL IS NAMED ON
     * EVERY LINE.** The single call it replaces was `balance()` with no arguments:
     * that *meant* the purchased pool while the ledger had one pool, and stopped
     * meaning it the day 3357 added the monthly one, while `MeResource`'s own
     * docblock went on insisting the field was purchased credit. **Neither
     * argument may be dropped here on the grounds that it is the obvious one**,
     * because the obvious one is what was wrong.
     *
     * ⚠️ **AND THE HARM THIS USED TO CLAIM WAS OVERSTATED IN A WAY WORTH FIXING
     * RATHER THAN DELETING** (3942). It said a client reading the old field *"to
     * decide whether a broadcast can start"* would have been told yes wrongly —
     * as though the purchased balance were that decision. It is not:
     * `BroadcastPreconditions` refuses on an unapproved 10DLC brand and on a
     * missing tenant-owned number **before** it ever reads a balance (3310), and
     * **this endpoint publishes neither of those**. So no field here answers that
     * question at all, which is a gap recorded at 3943 rather than a claim to
     * repeat. The real harm of 3422 needs no broadcast: a figure labelled
     * *purchased* that was in fact the monthly grant is a wrong number under a
     * name that says what it is, and every reader of it was misled about what
     * they own.
     *
     * ⚠️ **THE READS HAPPEN HERE RATHER THAN IN THE RESOURCE**, on the existing
     * rule in this file: a resource that queries is a resource whose cost depends
     * on where it is rendered, and this controller is the only place that already
     * knows a tenant is resolved. The resource is handed the numbers and decides
     * only how they are named and what unit they are declared in.
     *
     * ✅ **THE KEYS ARE THE ENUMS' OWN BACKING VALUES AND THE SHAPE BELOW IS
     * SPELT WITH LITERALS — THAT PAIR IS BUILD-CHECKED, NOT MERELY DOCUMENTED**
     * (3948). It looks like the two could drift on a rename, and they cannot:
     * PHPStan at level 8 resolves `CreditProduct::Sms->value` to the constant
     * `'sms'` and compares it against this method's declared array shape, so
     * renaming a backing value fails `composer stan` here with *"Array does not
     * have offset 'sms'"* rather than reaching a reader. **Verified by planting
     * the rename**, because a claim about a guard is worth nothing until the
     * guard has refused something.
     *
     * ⚠️ **SIX QUERIES, KNOWINGLY.** `CreditLedger` is the only file in `app/`
     * permitted to touch `CreditLedgerEntry` at all, so a single grouped read is
     * not this file's to write — and each of these is one indexed head-row lookup
     * on `(business_id, product, pool, id)`. **The wrong fix is a resource or a
     * controller computing a balance for itself**, which is decision 286's second
     * definition of one number.
     *
     * @return array{
     *     sms: array{monthly: int, topup: int},
     *     email: array{monthly: int, topup: int},
     *     ai: array{monthly: int, topup: int},
     * }
     */
    private function balances(): array
    {
        return [
            CreditProduct::Sms->value => [
                CreditPool::Monthly->value => $this->credits->balance(CreditProduct::Sms, CreditPool::Monthly),
                CreditPool::TopUp->value => $this->credits->balance(CreditProduct::Sms, CreditPool::TopUp),
            ],
            CreditProduct::Email->value => [
                CreditPool::Monthly->value => $this->credits->balance(CreditProduct::Email, CreditPool::Monthly),
                CreditPool::TopUp->value => $this->credits->balance(CreditProduct::Email, CreditPool::TopUp),
            ],
            CreditProduct::Ai->value => [
                CreditPool::Monthly->value => $this->credits->balance(CreditProduct::Ai, CreditPool::Monthly),
                CreditPool::TopUp->value => $this->credits->balance(CreditProduct::Ai, CreditPool::TopUp),
            ],
        ];
    }
}
