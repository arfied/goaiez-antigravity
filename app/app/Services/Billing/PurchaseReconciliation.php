<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\CreditSettlement;
use App\Enums\GatewayPaymentState;
use App\Enums\PaymentGateway;
use App\Enums\PurchaseReconciliationVerdict;
use App\Exceptions\AuthorizeNetRequestFailed;
use App\Exceptions\StripeRequestFailed;
use App\Models\CreditPurchase;
use App\Models\CreditPurchaseReconciliation;
use App\Models\CreditPurchaseReference;
use App\Services\AuditService;
use App\Services\Config\DefaultsRegistry;
use App\Support\Money;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The sweep for a payment whose notification never arrived — decision 3483, which
 * recorded this gap deliberately and left it open.
 *
 * ⛔ **A TENANT HAS PAID AND HAS NO CREDIT. THAT IS THE WORST OUTCOME THIS MODEL
 * CAN PRODUCE**, and until this class it needed a person to notice. 2056 makes
 * the webhook the source of truth, and 3457 records the consequence honestly: on
 * Authorize.Net a lost notification is simply lost, because **the vendor publishes
 * no webhook retry schedule**, so an `authorized` purchase carrying a transaction
 * id, an amount and a SKU could sit there for ever. This asks the gateway what
 * really happened and settles the ones that really were paid.
 *
 * ## The four properties this class lives or dies on
 *
 * ⛔ **1. IT MUST NEVER CREDIT SOMETHING THAT WAS NOT PAID.** It bypasses the
 * ordinary evidence path — a signed notification — so a sweep that misread a
 * status would hand out free credit at scale, on a schedule, unattended. Every
 * refusal is therefore the *default*: `Paid` is reached only by naming the exact
 * vendor states that mean "the money is ours", and everything else — including
 * every status neither vendor documents — lands on
 * {@see GatewayPaymentState::Ambiguous}, which does nothing and leaves the row for
 * a person. **Leaving a human to look is the correct outcome, and is what existed
 * before this class.**
 *
 * ⛔ **2. IT MUST NEVER DOUBLE-CREDIT.** It writes no ledger movement itself; it
 * calls {@see CreditPurchases::settle()}, whose conditional claim and partial
 * unique index (3458) already make a replay a no-op. **A sweep racing a late
 * notification is the case that matters**, and it is safe because both paths take
 * the same claim: whichever loses is told `AlreadySettled` and moves nothing.
 *
 * ⛔ **3. IT TAKES AND RETURNS NO MONEY.** No refund, no void, no capture — it
 * reads from the gateways and settles our own records. A payment the vendor says
 * was refunded is recorded and credits nobody (3484 leaves the refund path
 * unbuilt, and this does not quietly build half of it).
 *
 * ⛔ **4. A CHARGE THAT GENUINELY FAILED STOPS BEING SWEPT FOR EVER.** An
 * unbounded retry against a dead transaction is a rate-limit incident and a cost
 * with no ceiling. See the bounds below.
 *
 * ## What it will not do to a purchase row
 *
 * ⛔ **THE ONLY WRITE THIS CLASS MAKES TO `credit_purchases` IS THROUGH
 * `CreditPurchases::settle()`.** Marking a purchase `failed` because a gateway
 * said "declined" is the tidy answer and it is the dangerous one: `settle()`
 * claims `WHERE status IN ('pending','authorized')`, so a purchase this sweep
 * moved out of a settleable state could **never** be credited by a notification
 * that arrived afterwards. Everything else it concludes goes to
 * {@see CreditPurchaseReconciliation}. **The sweep must not be able to disarm the
 * path it exists to back up.**
 *
 * ## How it is bounded, and why age is not the give-up rule
 *
 *   don't look yet   {@see self::MINIMUM_AGE_MINUTES}. Below it the notification
 *                    is still expected, and on Stripe the Search API is
 *                    explicitly *"searchable in less than a minute … up to an
 *                    hour behind during outages"*, so an earlier read would be
 *                    asking a question the vendor cannot answer.
 *   give up          {@see self::MAXIMUM_ATTEMPTS} inconclusive reads. After that
 *                    the purchase gets one `abandoned` row, one audit entry and
 *                    one **warning log naming it**, and is never read again.
 *   don't scan       {@see self::LOOKBACK_DAYS}. The candidate enumeration has a
 *                    floor because an ever-growing scan is itself the unbounded
 *                    cost this section is about.
 *
 * ⚠️ **AGE IS DELIBERATELY NOT WHAT MAKES IT GIVE UP.** Abandoning a purchase for
 * being old, *before* asking the gateway, would refuse to credit somebody who
 * genuinely paid — which is the exact defect this class exists to fix. So a
 * fourteen-day-old purchase that has never been examined is still examined; what
 * ends the sweep's interest is having asked and not been answered.
 *
 * ## Why the candidates come from the un-tenanted index
 *
 * ⚠️ **`credit_purchases` IS `ENABLE`+`FORCE` RLS AND THIS RUNS WITH NO TENANT**,
 * so it cannot be queried directly — the wall 3477 and 682 both describe.
 * `credit_purchase_references` is the platform-scoped index a webhook already
 * resolves through, it carries `created_at`, and `CreditPurchases::open()` writes
 * it **in the same transaction as the purchase**. So a purchase missing from it is
 * a purchase no settlement path in this application could reach anyway, and the
 * sweep inherits exactly the blind spot the webhook already has rather than a new
 * one.
 *
 * ⚠️ **THAT IS THE NARROWING `AdvanceDunningSchedules` REFUSED (2595), AND THE
 * DIFFERENCE IS WHY IT IS TAKEN HERE.** There, enumerating a vendor index instead
 * of every business rested on a claim about *today's callers*, and being wrong
 * meant a tenant's dunning schedule was never picked up again. Here the index row
 * and the purchase row are written by one statement pair inside one transaction,
 * and the failure of that assumption is not silent: a purchase with no index row
 * cannot be settled by a notification either.
 */
final class PurchaseReconciliation
{
    /**
     * How long a purchase is left alone before it is asked about.
     *
     * ⚠️ **THIS NUMBER IS STRIPE'S, NOT A GUESS.** Its Search API is documented as
     * *"searchable in less than a minute"* under normal conditions and *"up to an
     * hour behind during outages"* — and reading a payment as missing during that
     * window is how a reconciliation invents a failure. Two hours is comfortably
     * past the documented worst case, and on Authorize.Net it is dwell time the
     * notification usually uses anyway.
     */
    public const int MINIMUM_AGE_MINUTES = 120;

    /**
     * How many inconclusive reads a purchase gets before the sweep stops.
     *
     * ⚠️ **AN ATTEMPT IS A ROW, NOT A COUNTER**, so two runs cannot lose one
     * another's increment. At the scheduled hourly cadence this is most of a
     * working day of asking — long enough for a held-for-review transaction to be
     * released or a settlement batch to close, short enough that a dead
     * transaction is not read for ever.
     */
    public const int MAXIMUM_ATTEMPTS = 8;

    /**
     * How far back the candidate enumeration reaches.
     *
     * ⚠️ **A FLOOR ON THE SCAN AND NOT A DEADLINE FOR A TENANT.** Anything older
     * has already had its attempts, its `abandoned` row and its warning; what this
     * prevents is a query whose cost grows for ever. ⚠️ **The consequence, stated
     * rather than assumed away**: if this sweep were switched off for longer than
     * this, the purchases that aged out during the outage are the operator's to
     * find — `credit_purchases` is indexed on `(business_id, status, id)` for
     * exactly that search (3483).
     */
    public const int LOOKBACK_DAYS = 90;

    /**
     * Gateway reads per run.
     *
     * ⚠️ **STRIPE PUBLISHES 20 READ OPERATIONS PER SECOND ACROSS EVERY SEARCH
     * ENDPOINT** (read 2026-08-14). These are sequential, so a hundred of them
     * cannot approach it; the cap is here because a queue of unreconciled
     * purchases means something is already wrong, and the answer to that is not a
     * thousand vendor calls in one minute.
     */
    public const int DEFAULT_BATCH = 100;

    /** How many index rows are read from the database at a time. */
    private const int CHUNK = 200;

    public function __construct(
        private readonly CreditPurchases $purchases = new CreditPurchases,
        private readonly AuthorizeNetApi $authorizeNet = new AuthorizeNetApi,
        private readonly StripeApi $stripe = new StripeApi,
        private readonly AuditService $audit = new AuditService,
        private readonly DefaultsRegistry $registry = new DefaultsRegistry,
    ) {}

    public function minimumAgeMinutes(): int
    {
        return $this->registry->int('billing.reconciliation.minimum_age_minutes');
    }

    public function lookbackDays(): int
    {
        return $this->registry->int('billing.reconciliation.lookback_days');
    }

    public function defaultBatch(): int
    {
        return $this->registry->int('billing.reconciliation.default_batch');
    }

    /**
     * Ask the gateways about every purchase where money may have moved and credit
     * did not, and settle the ones that really were paid.
     *
     * ⚠️ **RUNS OUTSIDE ANY TENANT AND ESTABLISHES ONE PER PURCHASE**, the same
     * shape the webhook handlers use: the index answers *whose*, and everything
     * after {@see Tenancy::actingAs()} is ordinarily scoped, RLS included.
     *
     * @param  int  $limit  Gateway reads this run.
     * @return list<CreditPurchaseReconciliation> What was written, in the order it
     *                                            was written.
     */
    public function sweep(?int $limit = null): array
    {
        $limit ??= $this->defaultBatch();
        $now = Carbon::now();
        $written = [];

        CreditPurchaseReference::query()
            ->whereBetween('created_at', [
                $now->copy()->subDays($this->lookbackDays()),
                $now->copy()->subMinutes($this->minimumAgeMinutes()),
            ])
            // Oldest first: a tenant who has been waiting longest for credit they
            // paid for is the one to serve when the batch cap binds.
            ->orderBy('created_at')
            ->orderBy('reference')
            ->chunk(self::CHUNK, function (Collection $references) use (&$written, $limit): bool {
                foreach ($references as $index) {
                    if (count($written) >= $limit) {
                        return false;
                    }

                    $row = Tenancy::actingAs(
                        $index->business_id,
                        fn (): ?CreditPurchaseReconciliation => $this->examine($index),
                    );

                    if ($row instanceof CreditPurchaseReconciliation) {
                        $written[] = $row;
                    }
                }

                return true;
            });

        // Never leave a security context established after a sweep —
        // `AdvanceDunningSchedules`' rule, and the Postgres session variable
        // outlives this process's connection under any pooler.
        Tenancy::forgetAll();

        return $written;
    }

    /**
     * One purchase, with its tenant established.
     *
     * Null means there was nothing to do: no purchase behind the index, a purchase
     * already settled or already failed, or one the sweep has already finished
     * with. **Silence here is the ordinary case** — most rows in the window were
     * credited by their notification within seconds.
     */
    private function examine(CreditPurchaseReference $index): ?CreditPurchaseReconciliation
    {
        $purchase = CreditPurchase::query()->where('reference', $index->reference)->first();

        if (! $purchase instanceof CreditPurchase) {
            return null;
        }

        // `credited`, `failed` and `mismatched` are all terminal at the purchase,
        // and `isSettleable()` is the same question `settle()`'s claim asks — so a
        // row this returns false for could not be credited by anything anyway.
        if (! $purchase->status->isSettleable()) {
            return null;
        }

        /** @var Collection<int, CreditPurchaseReconciliation> $attempts */
        $attempts = CreditPurchaseReconciliation::query()
            ->where('credit_purchase_id', $purchase->getKey())
            ->get();

        foreach ($attempts as $attempt) {
            if ($attempt->verdict->stopsTheSweep()) {
                return null;
            }
        }

        if ($attempts->count() >= self::MAXIMUM_ATTEMPTS) {
            return $this->abandon($purchase, $attempts->count());
        }

        return $this->apply($purchase, $this->ask($purchase));
    }

    /**
     * Turn the gateway's answer into a settlement or a record, and never both.
     */
    private function apply(CreditPurchase $purchase, GatewayPaymentReport $report): CreditPurchaseReconciliation
    {
        if ($report->state !== GatewayPaymentState::Paid
            || ! $report->paid instanceof Money
            || $report->transactionId === null) {
            return $this->record(
                $purchase,
                match ($report->state) {
                    GatewayPaymentState::NotPaid => PurchaseReconciliationVerdict::NotPaid,
                    GatewayPaymentState::Refunded => PurchaseReconciliationVerdict::Refunded,
                    GatewayPaymentState::Unreachable => PurchaseReconciliationVerdict::Unreachable,
                    // ⚠️ AND `Paid` REACHES HERE TOO, IF IT EVER ARRIVED WITHOUT AN
                    // AMOUNT OR A TRANSACTION ID. `GatewayPaymentReport::paid()`
                    // cannot be constructed without both, so this arm is
                    // unreachable today — and it is written as `Ambiguous` rather
                    // than as an exception because the safe reading of "paid, we
                    // think, somehow" is to credit nobody.
                    GatewayPaymentState::Ambiguous, GatewayPaymentState::Paid => PurchaseReconciliationVerdict::Ambiguous,
                },
                $report->gatewayStatus,
                $report->detail,
            );
        }

        /*
         * ⛔ THE SETTLEMENT GOES THROUGH THE FUNDER AND NOWHERE ELSE. `settle()`
         * takes the claim, compares what the gateway says was paid against the SKU
         * price recorded at intent (3461), writes the one permitted
         * `CreditKind::Purchase` row and audits it. Reimplementing any of that
         * here would be the second funder `BillingTest`'s lint exists to prevent,
         * wearing a reconciliation's clothes.
         */
        $settlement = $this->purchases->settle(
            $purchase->reference,
            $report->transactionId,
            $report->paid,
        );

        $verdict = match ($settlement) {
            CreditSettlement::Credited => PurchaseReconciliationVerdict::Credited,
            // ⚠️ THE RACE, AND IT IS A SUCCESS. A notification that arrived while
            // this read was in flight took the same claim; the balance moved once
            // and this attempt moved nothing.
            CreditSettlement::AlreadySettled => PurchaseReconciliationVerdict::AlreadySettled,
            CreditSettlement::Mismatched => PurchaseReconciliationVerdict::Mismatched,
            // The index resolved and the purchase did not, having been loaded a
            // moment earlier. Nothing here can mend that, and it is not a licence
            // to credit anybody.
            CreditSettlement::Unknown => PurchaseReconciliationVerdict::Ambiguous,
        };

        $row = $this->record($purchase, $verdict, $report->gatewayStatus, $report->detail);

        if ($verdict === PurchaseReconciliationVerdict::Credited) {
            /*
             * ⛔ THE AUDIT ENTRY THIS SLICE OWES. `CreditPurchases::credit()`
             * already writes `billing.credits_purchased` with the actor
             * `gateway:<vendor>` — which is true of every credit and does not say
             * that **this** one came from a reconciliation rather than from a
             * signed notification. Crediting a tenant off the back of a read we
             * initiated is exactly the act somebody will need to reconstruct.
             */
            $this->audit->record(
                'billing.purchase_reconciled',
                'reconciliation:'.$purchase->gateway->value,
                $purchase,
                [
                    'gateway_status' => $report->gatewayStatus,
                    'gateway_transaction_id' => $report->transactionId,
                    'paid_minor_units' => $report->paid->minorUnits,
                    'currency' => $report->paid->currency,
                    'product' => $purchase->product->value,
                ],
            );

            // ⚠️ A WARNING RATHER THAN AN INFO LINE, BECAUSE THIS MEANS A
            // NOTIFICATION WAS LOST. The tenant is now whole; the integration is
            // not, and a run of these is the signal that a webhook endpoint or a
            // vendor's delivery has broken.
            Log::warning('a credit purchase was settled by reconciliation rather than by a notification', [
                'business_id' => $purchase->business_id,
                'credit_purchase_id' => $purchase->getKey(),
                'gateway' => $purchase->gateway->value,
                'gateway_status' => $report->gatewayStatus,
            ]);
        }

        if ($verdict === PurchaseReconciliationVerdict::Mismatched) {
            // Money moved, nothing was credited, and a person settles it with an
            // `Adjust` or a refund (3462). The purchase row carries both figures;
            // this line is what puts it in front of somebody.
            Log::warning('a reconciled payment did not match the price of the pack it was for', [
                'business_id' => $purchase->business_id,
                'credit_purchase_id' => $purchase->getKey(),
                'gateway' => $purchase->gateway->value,
            ]);
        }

        return $row;
    }

    /**
     * The bound, reached.
     *
     * ⛔ **GIVING UP IS AN EVENT WITH A RECORD, AND THAT IS THE WHOLE POINT OF
     * THIS METHOD.** A silently abandoned paid-and-uncredited purchase is the
     * defect this class exists to fix, reappearing one level up — so it produces a
     * terminal row, an audit entry and a warning naming the purchase, the tenant,
     * the gateway, the transaction and what it cost. **The purchase row itself is
     * left settleable**, so a late notification or a person can still resolve it.
     */
    private function abandon(CreditPurchase $purchase, int $attempts): CreditPurchaseReconciliation
    {
        $detail = 'Gave up after '.$attempts.' inconclusive reads of the gateway. Nothing was '
            .'credited and the purchase is still settleable; a person has to decide what '
            .'happened to this payment.';

        $row = $this->record($purchase, PurchaseReconciliationVerdict::Abandoned, null, $detail);

        $this->audit->record(
            'billing.purchase_reconciliation_abandoned',
            'reconciliation:'.$purchase->gateway->value,
            $purchase,
            [
                'attempts' => $attempts,
                'gateway_transaction_id' => $purchase->gateway_transaction_id,
                'price_cents' => $purchase->price_cents,
                'currency' => $purchase->currency,
                'product' => $purchase->product->value,
                'status' => $purchase->status->value,
            ],
        );

        Log::warning('a credit purchase could not be reconciled and is no longer being swept', [
            'business_id' => $purchase->business_id,
            'credit_purchase_id' => $purchase->getKey(),
            'gateway' => $purchase->gateway->value,
            'gateway_transaction_id' => $purchase->gateway_transaction_id,
            'status' => $purchase->status->value,
            'price_cents' => $purchase->price_cents,
            'currency' => $purchase->currency,
            'attempts' => $attempts,
        ]);

        return $row;
    }

    private function record(
        CreditPurchase $purchase,
        PurchaseReconciliationVerdict $verdict,
        ?string $gatewayStatus,
        string $detail,
    ): CreditPurchaseReconciliation {
        $row = new CreditPurchaseReconciliation;

        // Guarded, so assigned rather than filled: the purchase this points at is
        // what decides whose balance a later reading of these rows describes.
        $row->credit_purchase_id = (int) $purchase->getKey();

        $row->fill([
            'verdict' => $verdict,
            'gateway_status' => $gatewayStatus === null ? null : Str::limit($gatewayStatus, 60),
            'detail' => Str::limit($detail, 250),
            'attempted_at' => Carbon::now(),
        ]);

        $row->save();

        return $row;
    }

    /**
     * Ask the gateway that took the money.
     */
    private function ask(CreditPurchase $purchase): GatewayPaymentReport
    {
        return match ($purchase->gateway) {
            PaymentGateway::AuthorizeNet => $this->askAuthorizeNet($purchase),
            PaymentGateway::Stripe => $this->askStripe($purchase),
        };
    }

    /**
     * `getTransactionDetailsRequest` — see {@see AuthorizeNetApi::transactionDetails()}
     * for what was read from the vendor and when.
     *
     * ⛔ **PAID MEANS `capturedPendingSettlement` OR `settledSuccessfully`, AND
     * `responseCode` 1 AS WELL.** Those two are the only members of the vendor's
     * `transactionStatusEnum` that mean the money is captured and ours. The enum
     * has twenty-four members, and the ones a hurried reading would wave through
     * are the reason this list is written out rather than inferred:
     * `authorizedPendingCapture` is a hold, `underReview`, `FDSPendingReview` and
     * `approvedReview` are a merchant's decision that has not been made,
     * `settlementError` is a batch that **failed** to settle, and `expired` is an
     * authorisation nobody captured in time. None of those is "paid" and each of
     * them can carry `responseCode` 1.
     */
    private function askAuthorizeNet(CreditPurchase $purchase): GatewayPaymentReport
    {
        $transactionId = $purchase->gateway_transaction_id;

        if ($transactionId === null || $transactionId === '') {
            // ⚠️ THE LOST-RESPONSE SHAPE, AND THIS GATEWAY CANNOT ANSWER IT.
            // `getTransactionDetailsRequest` takes a `transId` and there is no
            // lookup by `refId` — 3451's missing idempotency key, met from the
            // reconciliation side. It is a person's job in the merchant
            // interface, and the attempt bound is what stops us pretending
            // otherwise.
            return GatewayPaymentReport::ambiguous(
                null,
                'This purchase has no Authorize.Net transaction id, so the vendor cannot be '
                .'asked about it: the charge response was lost and there is no lookup by '
                .'our own reference on this gateway.'
            );
        }

        try {
            $transaction = $this->authorizeNet->transactionDetails($purchase->business_id, $transactionId);
        } catch (AuthorizeNetRequestFailed $e) {
            // The code, never the vendor's text — `AuthorizeNetRequestFailed`
            // exists because `errorText` quotes the value it rejected.
            return GatewayPaymentReport::unreachable('Authorize.Net could not be asked: '.$e->reason.'.');
        }

        if ($transaction === null) {
            return GatewayPaymentReport::ambiguous(
                null,
                'Authorize.Net returned no transaction for an id taken from its own charge '
                .'response. Nothing is decided from an answer like that.'
            );
        }

        $status = $this->stringAt($transaction, 'transactionStatus');
        $responseCode = $this->stringAt($transaction, 'responseCode');

        $reversed = [
            'refundPendingSettlement',
            'refundSettledSuccessfully',
            'returnedItem',
            'chargeback',
        ];

        if ($status !== null && in_array($status, $reversed, true)) {
            return GatewayPaymentReport::refunded(
                $status,
                'The payment was reversed at the gateway, so no credit is owed for it. '
                .'Returning money is not something this sweep does.'
            );
        }

        if ($status !== null && in_array($status, ['declined', 'voided', 'expired'], true)) {
            return GatewayPaymentReport::notPaid(
                $status,
                'Authorize.Net says no money moved on this transaction.'
            );
        }

        if (! in_array($status, ['capturedPendingSettlement', 'settledSuccessfully'], true)) {
            return GatewayPaymentReport::ambiguous(
                $status,
                'The transaction is in a state that is neither paid nor finished — held for '
                .'review, still authorising, or one this application does not model. Nothing '
                .'is credited from a state like that.'
            );
        }

        if ($responseCode !== '1') {
            // ⚠️ 3449's SECOND FLOOR, ON A READ. The two fields answer different
            // questions — where the money is now, and what the processor said at
            // authorisation — and a settled-looking transaction that was never
            // approved is not one this sweep resolves.
            return GatewayPaymentReport::ambiguous(
                $status,
                'The transaction reads as captured and its response code is not an approval, '
                .'which is a disagreement only a person should settle.'
            );
        }

        // `settleAmount` is what actually settled and `authAmount` is what was
        // authorised; before the nightly batch closes only the second exists.
        $paid = $this->minorUnits($transaction['settleAmount'] ?? null)
            ?? $this->minorUnits($transaction['authAmount'] ?? null);

        if ($paid === null) {
            return GatewayPaymentReport::ambiguous(
                $status,
                'Neither settleAmount nor authAmount came back as an amount this application '
                .'can read exactly, and money is never rounded into existence here.'
            );
        }

        $currency = app(DefaultsRegistry::class)->value('billing.currency');

        if (! is_string($currency) || $currency === '') {
            return GatewayPaymentReport::ambiguous(
                $status,
                'The merchant account currency is not configured, and Authorize.Net does not '
                .'state one on a transaction, so no comparable amount can be formed.'
            );
        }

        return GatewayPaymentReport::paid(
            Money::of($paid, $currency),
            $transactionId,
            $status,
            'Authorize.Net says this transaction was captured.'
        );
    }

    /**
     * `GET /v1/payment_intents/search` — see
     * {@see StripeApi::paymentIntentsForReference()} for what was read and when,
     * including why the status is deliberately not part of the query.
     *
     * ⚠️ **THE STRIPE FAILURE THIS SWEEP MEETS IS `pending`, NOT `authorized`.**
     * Checkout takes the money on Stripe's own page, so a purchase whose
     * `checkout.session.completed` was lost never reaches `authorized` at all — it
     * sits at `pending`, indistinguishable on our side from a checkout somebody
     * closed. **Only the vendor can tell those apart**, which is why an empty
     * search is ambiguous here rather than a failure.
     */
    private function askStripe(CreditPurchase $purchase): GatewayPaymentReport
    {
        try {
            $intents = $this->stripe->paymentIntentsForReference(
                $purchase->reference,
                $purchase->business_id,
            );
        } catch (StripeRequestFailed $e) {
            return GatewayPaymentReport::unreachable('Stripe could not be asked: '.$e->reason.'.');
        }

        if ($intents === []) {
            return GatewayPaymentReport::ambiguous(
                null,
                'Stripe knows no payment carrying this purchase reference. An unfinished '
                .'checkout looks exactly like this, and so does a search index that has not '
                .'caught up, so nothing is concluded from it.'
            );
        }

        if (count($intents) > 1) {
            return GatewayPaymentReport::ambiguous(
                null,
                'More than one Stripe payment carries this purchase reference. Which of them '
                .'this pack was sold for is a question for a person.'
            );
        }

        $intent = $intents[0];
        $status = $this->stringAt($intent, 'status');

        if ($status === 'canceled') {
            return GatewayPaymentReport::notPaid($status, 'Stripe cancelled this payment; no money moved.');
        }

        if ($status !== 'succeeded') {
            return GatewayPaymentReport::ambiguous(
                $status,
                'The payment has not succeeded. It may still, and it may not, and neither is '
                .'a reason to credit anybody now.'
            );
        }

        $charge = $intent['latest_charge'] ?? null;

        if (! is_array($charge)) {
            // ⛔ WITHOUT THE EXPANDED CHARGE, A REFUND IS INVISIBLE. A refunded
            // payment leaves its PaymentIntent `succeeded` with `amount_received`
            // untouched, so crediting on the strength of this object alone would
            // hand credit to somebody who has had their money back.
            return GatewayPaymentReport::ambiguous(
                $status,
                'Stripe returned the payment without its charge expanded, so a refund cannot '
                .'be ruled out and nothing is credited.'
            );
        }

        $refunded = ($charge['refunded'] ?? null) === true
            || (is_int($charge['amount_refunded'] ?? null) && $charge['amount_refunded'] > 0);

        if ($refunded) {
            return GatewayPaymentReport::refunded(
                $status,
                'The payment was refunded at Stripe, so no credit is owed for it. Returning '
                .'money is not something this sweep does.'
            );
        }

        $received = $intent['amount_received'] ?? null;
        $currency = $this->stringAt($intent, 'currency');
        $transactionId = $this->stringAt($intent, 'id');

        if (! is_int($received) || $received <= 0 || $currency === null || $transactionId === null) {
            return GatewayPaymentReport::ambiguous(
                $status,
                'The payment succeeded and did not state an amount, a currency and an id this '
                .'application can read. Nothing is credited from a partial answer.'
            );
        }

        return GatewayPaymentReport::paid(
            // Stripe is integer minor units already — there is no decimal on this
            // gateway and nothing here converts one.
            Money::of($received, strtoupper($currency)),
            $transactionId,
            $status,
            'Stripe says this payment succeeded and has not been refunded.'
        );
    }

    /**
     * One of Authorize.Net's decimal amounts as integer minor units.
     *
     * ⛔ **NO FLOAT ARITHMETIC ON MONEY** (`18` §Money handling, row 22's
     * build-failing gate, 3452). `(int) (0.29 * 100)` is `28` in IEEE-754 and the
     * error appears for some amounts and not others, which is the worst possible
     * distribution for the comparison that decides whether somebody is credited.
     *
     * ⚠️ **FOUR FRACTIONAL DIGITS, NOT TWO, AND THAT IS THE DIFFERENCE FROM THE
     * WEBHOOK PATH.** `AnetApiSchema.xsd` defines `authAmount` and `settleAmount`
     * as *"decimal element with minimum inclusive value of 0.00 and 4 fractional
     * digits"* — the notification payload's own `authAmount` is two, so the
     * converter in {@see AuthorizeNetWebhooks} would refuse `45.0000` outright.
     *
     * ⚠️ **AND A NON-ZERO SUB-CENT DIGIT REFUSES RATHER THAN ROUNDS.** This
     * application has no sub-cent denomination for money, and an amount that
     * cannot be represented exactly must not become one that can by being rounded
     * into the comparison that decides a credit.
     */
    private function minorUnits(mixed $raw): ?int
    {
        if (! is_int($raw) && ! is_float($raw) && ! is_string($raw)) {
            return null;
        }

        // `number_format` at a fixed scale is a string conversion rather than an
        // arithmetic one: the rounding happens in the formatter instead of in a
        // multiply whose error depends on the value.
        $normalised = is_string($raw) ? trim($raw) : number_format((float) $raw, 4, '.', '');

        if (preg_match('/^(\d+)(?:\.(\d{1,4}))?$/', $normalised, $matches) !== 1) {
            return null;
        }

        $fraction = str_pad($matches[2] ?? '', 4, '0');

        if (substr($fraction, 2) !== '00') {
            return null;
        }

        $cents = (int) $matches[1] * 100 + (int) substr($fraction, 0, 2);

        return $cents > 0 ? $cents : null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function stringAt(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        if (is_string($value) && $value !== '') {
            return $value;
        }

        // Both vendors send an id or a code as a JSON number in some responses and
        // a string in others — `AuthorizeNetApi::stringAt()`'s reasoning, and
        // `responseCode` in particular arrives both ways.
        return is_int($value) ? (string) $value : null;
    }
}
