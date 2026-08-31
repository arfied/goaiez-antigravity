<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\AutopilotActionType;
use App\Enums\CreditKind;
use App\Enums\CreditPurchaseStatus;
use App\Enums\CreditSettlement;
use App\Enums\PaymentGateway;
use App\Exceptions\CreditPurchaseRefused;
use App\Models\CreditPurchase;
use App\Models\CreditPurchaseReference;
use App\Services\ActivityService;
use App\Services\AuditService;
use App\Support\Billing\PurchaseConfirmation;
use App\Support\Billing\TopUpSku;
use App\Support\Money;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The funder: the one place in this application that turns money into credit
 * (decisions 3102, 3426, 3447).
 *
 * ⛔ **IT IS THE ONLY FILE IN `app/` PERMITTED TO CONSTRUCT
 * `CreditKind::Purchase`, AND A `BillingTest` LINT SAYS SO.** That lint used to
 * forbid the case outright — *"a green run here is a statement about the product:
 * this application cannot sell a credit"* — and it is narrowed rather than
 * deleted, to exactly this file, on the shape 3338 used for `Grant`. What it
 * still protects is the thing that mattered: a second funder cannot appear beside
 * the lint that would have caught it.
 *
 * ## The charge and the credit are two events, and 2056 decides which one counts
 *
 * ⚠️ **THE CREDIT IS WRITTEN FROM THE WEBHOOK, NEVER FROM THE SYNCHRONOUS
 * RESPONSE.** *"Webhooks stay the source of truth on both"* is not a preference
 * here: a charge whose response is lost in flight has still taken the money, and a
 * response that arrives has still not been reconciled by the vendor. So
 * {@see self::open()} writes a `pending` row before anything is charged,
 * {@see self::recordAuthorization()} records that the money moved, and
 * {@see self::settle()} — reached only from a verified notification — is the only
 * thing that touches {@see CreditLedger}.
 *
 * ⛔ **AND THAT MAKES "PAID BUT UNCREDITED" A STATE RATHER THAN A GAP.** A charge
 * that succeeds and a notification that never arrives leaves an `authorized` row
 * carrying the transaction id, the amount and the SKU. It is not silent, it is not
 * lost, and it is not credited — which is the right direction, because the reverse
 * failure credits somebody twice into an append-only table.
 *
 * ## Idempotency, in two layers, and the second one is the database
 *
 * ⚠️ **A REPLAY MUST NEVER DOUBLE-CREDIT.** Both gateways retry and both can
 * deliver out of order.
 *
 *   1. **The claim is a conditional `UPDATE`** — `WHERE id = ? AND status IN
 *      ('pending','authorized')` — and the number of affected rows *is* the
 *      answer. That is 350's lesson in the shape an existing row allows: a
 *      check-then-insert holds only sequentially, an atomic write holds under
 *      concurrency. `MessageCostLedger`'s `insertOrIgnore` on an idempotency key
 *      is the same argument for a row that does not exist yet.
 *   2. **A partial unique index on `credit_ledger`** —
 *      `credit_ledger_one_credit_per_purchase`, on `(business_id, ref_type,
 *      ref_id) WHERE kind = 'purchase'` — makes a second purchase row for one
 *      purchase impossible whatever this file says.
 *
 * ⚠️ **THE GATEWAY EVENT TABLES ARE A THIRD LAYER AND ARE NOT SUFFICIENT.**
 * `stripe_events` and `authorize_net_events` already refuse a *repeated event
 * id* — but two *different* events can describe one payment, which is precisely
 * how a same-transaction double credit would arrive, so the claim keys on the
 * purchase and not on the notification.
 *
 * ## What is verified before a balance moves
 *
 * ⛔ **THE AMOUNT PAID IS COMPARED AGAINST THE SKU PRICE RECORDED AT INTENT.** A
 * settlement that credited whatever units the row asked for, on the strength of a
 * notification claiming any amount at all, is a free-credit hole — a cent paid,
 * ten thousand messages granted. The price and the units were both read from the
 * registry when the purchase was opened, so the only question left at settlement
 * is whether the two agree, and a disagreement stops the credit and keeps the
 * money visible ({@see CreditPurchaseStatus::Mismatched}).
 *
 * ⚠️ **THE CURRENCY IS PART OF THE COMPARISON**, through {@see Money}: 5,000 of
 * some other currency is not $50, and comparing bare integers would say it was.
 *
 * ## Where the pool and the units come from
 *
 * ⛔ **A PURCHASE CREDITS THE TOP-UP POOL AND NEVER THE MONTHLY GRANT** (3307).
 * That is not asserted here — {@see CreditKind::poolWhenCredited()} answers it and
 * `credit_ledger_purchase_is_top_up` refuses the alternative at the database.
 * Paid credit with an expiry date on it is unrecoverable in an append-only table.
 *
 * ⛔ **THE UNITS ARRIVE ALREADY CONVERTED AND NOTHING HERE MULTIPLIES ANYTHING**
 * (3331, 3420). `TopUpCatalog` is the one place a registry seed becomes a ledger
 * figure, through {@see App\Enums\CreditProduct::ledgerUnitsFromGrant()}. A second
 * conversion here — even a correct one — is how the factor drifts, and the balance
 * looks plausible either way.
 */
final class CreditPurchases
{
    /**
     * How long our own handle is, in bytes of randomness.
     *
     * ⚠️ **THE CEILING IS AUTHORIZE.NET's `refId` AT 20 CHARACTERS**, verified
     * against the vendor's API reference (read 2026-08-14) and already recorded in
     * {@see AuthorizeNetApi}. The whole reference has to fit, because that is what
     * comes back as `payload.merchantReferenceId` on a notification — so the
     * prefix and the random part are budgeted together and
     * {@see self::reference()} is the only thing that mints one.
     */
    private const int REFERENCE_RANDOM = 12;

    public function __construct(
        private readonly CreditLedger $ledger = new CreditLedger,
        private readonly AuditService $audit = new AuditService,
        private readonly ActivityService $activity = new ActivityService,
    ) {}

    /**
     * Open a purchase, before anything is charged.
     *
     * ⛔ **THE CONFIRMATION IS A REQUIRED PARAMETER AND CONFIRM IS WHY.**
     * `CLAUDE.md` reserves CONFIRM for three things and *"anything that spends
     * money"* is one of them; a top-up is the plainest instance of it in the
     * product. The parameter is a {@see PurchaseConfirmation} rather than a
     * boolean for `TrialGrantAuthorization`'s reason — an `if ($confirmed)` inside
     * a method is a line somebody can delete with no test noticing, and a type
     * that cannot be constructed without an actor, a wording and an amount is a
     * lie somebody has to write on the line above.
     *
     * ⚠️ **AND THE AMOUNT IS COMPARED, NOT MERELY STORED.** A confirmation that
     * agreed to $50 cannot open a $250 purchase. Prices are admin-editable (3415),
     * so the figure genuinely can move between the screen and this call, and
     * charging the new one would be *"billing by surprise"* with a confirmation
     * record on file appearing to authorise it.
     *
     * @throws CreditPurchaseRefused
     */
    public function open(
        TopUpSku $sku,
        PaymentGateway $gateway,
        PurchaseConfirmation $confirmation,
    ): CreditPurchase {
        $businessId = Tenancy::idOrFail();

        if (! $confirmation->amountShown->equals($sku->price)) {
            throw CreditPurchaseRefused::because(
                'The amount confirmed is not what this top-up costs. A price that moved '
                .'between the screen and the charge is exactly the surprise a '
                .'confirmation exists to prevent, so the purchase is refused and the '
                .'person is asked again at the price that now applies.'
            );
        }

        $reference = $this->reference($businessId);

        return DB::transaction(function () use ($sku, $gateway, $confirmation, $reference, $businessId): CreditPurchase {
            $purchase = new CreditPurchase;
            $purchase->fill([
                'product' => $sku->product,
                'tier' => $sku->tier,
                'gateway' => $gateway,
                'status' => CreditPurchaseStatus::Pending,
                'reference' => $reference,
            ]);

            // Guarded, so assigned rather than filled — every one of these is
            // what the purchase is *for*, and a mass-assignable `units` reached
            // through a form request is a free-credit hole. `CreditLedger::write()`
            // guards `balance_after` for the same reason one table over.
            $purchase->price_cents = $sku->price->minorUnits;
            $purchase->currency = $sku->price->currency;
            $purchase->units = $sku->units;
            $purchase->grant_seed = $sku->grantSeed;
            $purchase->confirmed_actor = $confirmation->actor;
            $purchase->confirmed_amount_cents = $confirmation->amountShown->minorUnits;
            $purchase->confirmation_wording = $confirmation->wording;
            $purchase->confirmed_at = $confirmation->confirmedAt;
            $purchase->save();

            // The index, in the same transaction, for `stripe_customers`' reason:
            // a tenant row with no index means every notification for this
            // purchase resolves to nothing and is filed unlinked, and an index
            // with no purchase is a pointer at a row that does not exist.
            CreditPurchaseReference::query()->create([
                'reference' => $reference,
                'business_id' => $businessId,
                'gateway' => $gateway->value,
                'created_at' => Carbon::now(),
            ]);

            return $purchase;
        });
    }

    /**
     * Record that the gateway took the money, before any credit is written.
     *
     * ⚠️ **THIS IS NOT A SETTLEMENT AND MUST NOT BECOME ONE.** It is what the
     * synchronous response tells us on Authorize.Net — the charge succeeded and
     * has a transaction id — and 2056 makes it a *fact about the money*, never
     * about the credit. Crediting here would be quicker, would work almost every
     * time, and would double-credit the moment the notification also arrived.
     *
     * ⚠️ **THE TRANSACTION ID GOES ON THE INDEX TOO**, because it is the handle a
     * tenantless notification resolves through, and on this gateway it is the
     * *reliable* one: `merchantReferenceId` has a public history of disappearing
     * from notification payloads for days at a time.
     */
    public function recordAuthorization(CreditPurchase $purchase, string $transactionId): void
    {
        DB::transaction(function () use ($purchase, $transactionId): void {
            $purchase->status = CreditPurchaseStatus::Authorized;
            $purchase->gateway_transaction_id = $transactionId;
            $purchase->authorized_at = Carbon::now();
            $purchase->save();

            CreditPurchaseReference::query()
                ->whereKey($purchase->reference)
                ->update(['gateway_transaction_id' => $transactionId]);
        });
    }

    /**
     * Record that no money moved, and why.
     *
     * ⚠️ **A FAILED PURCHASE IS NOT REUSED.** A second attempt opens a second row,
     * so it gets its own reference and its own idempotency handle at the vendor —
     * reusing this one would make two genuinely different operations
     * indistinguishable to Stripe's `Idempotency-Key`, which would replay the
     * first attempt's failure at somebody who has since fixed their card.
     */
    public function recordFailure(CreditPurchase $purchase, string $reason): void
    {
        $purchase->status = CreditPurchaseStatus::Failed;
        $purchase->failure_reason = Str::limit($reason, 250);
        $purchase->save();
    }

    /**
     * Which business a notification belongs to, read before any tenant exists.
     *
     * ⚠️ **THE TRANSACTION ID FIRST AND THE REFERENCE SECOND, AND THE ORDER IS THE
     * VENDORS'.** On Authorize.Net the transaction id is what we recorded from the
     * synchronous charge, and `merchantReferenceId` is documented-by-the-community
     * as a field that has gone missing from live payloads for days; on Stripe
     * there is no transaction id until the notification itself, and the reference
     * rides in session metadata on every event. Asking both makes one handler work
     * on two gateways without either being trusted alone.
     */
    public function referenceFor(?string $transactionId, ?string $reference): ?CreditPurchaseReference
    {
        if ($transactionId !== null && $transactionId !== '') {
            $row = CreditPurchaseReference::query()
                ->where('gateway_transaction_id', $transactionId)
                ->first();

            if ($row instanceof CreditPurchaseReference) {
                return $row;
            }
        }

        if ($reference === null || $reference === '') {
            return null;
        }

        return CreditPurchaseReference::query()->whereKey($reference)->first();
    }

    /**
     * Apply a verified payment notification exactly once.
     *
     * ⚠️ **CALLED INSIDE `Tenancy::actingAs()`**, by a webhook handler that has
     * just resolved the tenant from {@see self::referenceFor()}. Everything below
     * is ordinarily scoped, RLS included.
     *
     * ⛔ **NOTHING THROWS OUT OF HERE.** Every refusal is deterministic — a wrong
     * amount will be just as wrong on the redelivery — so an exception would ask
     * the gateway to retry a decision that was made correctly and would read as an
     * infrastructure fault (3239). The refusals become an outcome and a row.
     *
     * @param  Money  $paid  What the gateway says was actually taken.
     */
    public function settle(
        string $reference,
        string $transactionId,
        Money $paid,
    ): CreditSettlement {
        $purchase = CreditPurchase::query()->where('reference', $reference)->first();

        if (! $purchase instanceof CreditPurchase) {
            // The index resolved a tenant and the purchase is not there. That is
            // a real inconsistency rather than an ordinary miss — recorded as
            // unknown rather than thrown, because a retry cannot mend it.
            return CreditSettlement::Unknown;
        }

        return DB::transaction(function () use ($purchase, $transactionId, $paid): CreditSettlement {
            /*
             * ⛔ THE CLAIM. The `WHERE` is the whole idempotency argument: a
             * redelivered notification finds the row already `credited`, updates
             * nothing, and is told so. Reading the status and then writing it
             * would hold only sequentially, which is 350's lesson and the reason
             * `MessageCostLedger` uses `insertOrIgnore` rather than a lookup.
             *
             * ⚠️ IT IS TAKEN BEFORE THE AMOUNT IS CHECKED, DELIBERATELY. Both
             * paths past this line are terminal — credited or mismatched — so
             * claiming first means a redelivery of a mismatched payment is a
             * no-op rather than a second row and a second alert about the same
             * cent.
             */
            $claimed = CreditPurchase::query()
                ->whereKey($purchase->getKey())
                ->whereIn('status', [
                    CreditPurchaseStatus::Pending->value,
                    CreditPurchaseStatus::Authorized->value,
                ])
                ->update([
                    'status' => CreditPurchaseStatus::Authorized->value,
                    // ⚠️ THE ONE WE ALREADY HAVE WINS. On Authorize.Net the id
                    // came from the synchronous charge, and a notification that
                    // resolved through `merchantReferenceId` instead could carry a
                    // different one — overwriting would lose the handle a refund
                    // and a reconciliation are issued against, in favour of a
                    // second one that arrived later. Set only when there is none,
                    // which is the Stripe path and the lost-response path.
                    'gateway_transaction_id' => $purchase->gateway_transaction_id ?? $transactionId,
                    'authorized_at' => $purchase->authorized_at ?? Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]) === 1;

            if (! $claimed) {
                return CreditSettlement::AlreadySettled;
            }

            $purchase->refresh();

            /*
             * ⛔ THE INDEX LEARNS THE TRANSACTION ID HERE, AND WITHOUT THIS LINE
             * EVERY STRIPE REVERSAL RESOLVES TO NOTHING (decision 6383).
             * {@see self::recordAuthorization()} writes it on the Authorize.Net
             * path, from the synchronous charge response — and **that path does
             * not exist on Stripe**, where Checkout takes the money on Stripe's
             * own page and the notification is the first thing we hear. So the
             * index row for every Stripe purchase carried a null
             * `gateway_transaction_id` for its whole life, while
             * `credit_purchases` had one.
             *
             * ⚠️ THAT WAS INVISIBLE UNTIL SOMETHING NEEDED TO RESOLVE AN EVENT
             * THAT IS NOT A SETTLEMENT. A top-up settles through the session's
             * `metadata.credit_purchase`, so the transaction id was never the
             * handle; a refund or a dispute carries no metadata of ours at all —
             * neither Stripe's Refund object nor its Dispute object inherits a
             * PaymentIntent's metadata, verified against both references on
             * 2026-08-20 — and the PaymentIntent id is the only handle they
             * share with us. {@see CreditClawbacks} is what needed it.
             *
             * ⚠️ `whereNull` RATHER THAN AN OVERWRITE, WHICH IS THE SAME RULE AS
             * THE LINE ABOVE: the one we already have wins, because on
             * Authorize.Net it came from the charge itself and a notification
             * that resolved through `merchantReferenceId` could carry another.
             */
            CreditPurchaseReference::query()
                ->whereKey($purchase->reference)
                ->whereNull('gateway_transaction_id')
                ->update(['gateway_transaction_id' => $purchase->gateway_transaction_id]);

            if (! $paid->equals($purchase->price())) {
                return $this->refuseOnAmount($purchase, $paid);
            }

            return $this->credit($purchase);
        });
    }

    /**
     * Move the top-up pool, and record it twice over.
     *
     * ⚠️ **THE ACTIVITY LINE AND THE AUDIT ENTRY ARE BOTH REQUIRED.** `CLAUDE.md`:
     * every automated action reaches the activity feed and every sensitive action
     * reaches the append-only audit log. **A purchase is both** — it happened
     * without the owner watching, and it moved their money.
     */
    private function credit(CreditPurchase $purchase): CreditSettlement
    {
        $actor = 'gateway:'.$purchase->gateway->value;

        $entry = $this->ledger->record(
            $purchase->product,
            // ⛔ THE ONE CONSTRUCTION OF THIS CASE IN `app/`. See the class
            // docblock and `BillingTest`'s funding lint, which names this file.
            CreditKind::Purchase,
            // Already in ledger units, converted once in TopUpCatalog. Nothing
            // here multiplies by a hundred and nothing here may start to.
            $purchase->units,
            $actor,
            null,
            // The reference the partial unique index keys on. Without both halves
            // the index cannot see a duplicate at all — and `credit_ledger`'s own
            // CHECK refuses half a reference anyway.
            $purchase::class,
            (int) $purchase->getKey(),
        );

        $purchase->status = CreditPurchaseStatus::Credited;
        $purchase->credited_at = Carbon::now();
        $purchase->save();

        $this->audit->record('billing.credits_purchased', $actor, $purchase, [
            'product' => $purchase->product->value,
            'tier' => $purchase->tier->value,
            'units' => $purchase->units,
            'price_cents' => $purchase->price_cents,
            'currency' => $purchase->currency,
            'balance_after' => $entry->balance_after,
            'gateway_transaction_id' => $purchase->gateway_transaction_id,
        ]);

        // ⚠️ `SystemMessage` WITH ITS OWN TITLE, NOT `SupportMadeAChange`. A
        // top-up is the owner's own act reaching them from the outside, and
        // filing it under the support vocabulary would put "GO AI EZ support made
        // a change" in the history of a person who bought something themselves.
        // The title carries the product and never a card detail — an activity
        // payload holds no personal data (`22`, and the broadcast payload rules).
        $this->activity->record(
            AutopilotActionType::SystemMessage,
            title: 'Your '.$purchase->product->value.' credit top-up went through',
        );

        return CreditSettlement::Credited;
    }

    /**
     * Money moved, the amount is not the SKU's, and nothing is credited.
     *
     * ⛔ **THE ROW STAYS VISIBLE AND SAYS BOTH FIGURES.** Somebody has been
     * charged; a support operator settles it with an `Adjust` or a refund, and
     * they need to know what was expected as well as what arrived. Collapsing this
     * into `failed` would hide a taken payment behind a word meaning the opposite.
     */
    private function refuseOnAmount(CreditPurchase $purchase, Money $paid): CreditSettlement
    {
        $purchase->status = CreditPurchaseStatus::Mismatched;
        $purchase->failure_reason = 'Paid '.$paid->minorUnits.' '.$paid->currency
            .' against a SKU priced '.$purchase->price_cents.' '.$purchase->currency
            .'. No credit was written; the payment needs a person.';
        $purchase->save();

        return CreditSettlement::Mismatched;
    }

    /**
     * Our own handle for one attempt.
     *
     * ⚠️ **IT LEADS WITH THE BUSINESS ID FOR `AuthorizeNetApi::send()`'s REASON**
     * — *"a correlation handle for a human reconciling a lost response"* — and it
     * fits inside the vendor's 20 characters, which is the binding constraint on
     * both gateways because Stripe's metadata limit is far larger.
     *
     * ⚠️ **AND IT IS RANDOM RATHER THAN DERIVED.** A deterministic handle would
     * make a second attempt after a failure indistinguishable from the first at
     * the vendor, and Stripe would replay the first attempt's response at somebody
     * who has since fixed their card.
     */
    private function reference(int $businessId): string
    {
        return mb_substr('b'.$businessId.'-'.Str::lower(Str::random(self::REFERENCE_RANDOM)), 0, 20);
    }
}
