<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\GatewayPaymentState;
use App\Support\Money;

/**
 * One gateway's answer to *"what actually happened to this charge"*, normalised.
 *
 * ⛔ **THE ONLY CONSTRUCTOR THAT CARRIES AN AMOUNT IS {@see self::paid()}, AND
 * THAT IS THE SAFETY PROPERTY OF THIS CLASS.** Every other outcome is
 * *incapable* of naming a figure, so no refusal path can accidentally be read as
 * money — a `?Money` that some branches happened to fill in would be one careless
 * `if` away from crediting a declined transaction. {@see PurchaseReconciliation}
 * hands `paid` to `CreditPurchases::settle()` and to nothing else, where it is
 * compared against the SKU price recorded at intent (3461).
 *
 * ⚠️ **`gatewayStatus` IS THE VENDOR'S OWN WORD AND IS NEVER INTERPRETED TWICE.**
 * `settledSuccessfully`, `capturedPendingSettlement`, `succeeded`, `canceled`. It
 * is stored so that an operator reading a reconciliation row has the term they can
 * search the vendor's own documentation for; every decision this application makes
 * has already been made by the time one of these exists.
 *
 * ⚠️ **`detail` IS OURS AND NEVER THE VENDOR'S TEXT.** Authorize.Net's `errorText`
 * quotes the value it rejected, which is why {@see App\Exceptions\AuthorizeNetRequestFailed}
 * carries a code instead — the same rule applies to a sentence that lands on a row
 * an operator reads.
 */
final readonly class GatewayPaymentReport
{
    private function __construct(
        public GatewayPaymentState $state,
        public ?Money $paid,
        public ?string $transactionId,
        public ?string $gatewayStatus,
        public string $detail,
    ) {}

    /**
     * The money moved and is ours.
     *
     * @param  Money  $paid  What the gateway says was actually taken — never what
     *                       the purchase expected. The comparison belongs to
     *                       `CreditPurchases::settle()`.
     * @param  string  $transactionId  The handle a refund or a support query is
     *                                 issued against: the vendor's transaction id
     *                                 on Authorize.Net, the PaymentIntent id on
     *                                 Stripe — the same one the webhook path
     *                                 records.
     */
    public static function paid(Money $paid, string $transactionId, string $gatewayStatus, string $detail): self
    {
        return new self(GatewayPaymentState::Paid, $paid, $transactionId, $gatewayStatus, $detail);
    }

    public static function notPaid(?string $gatewayStatus, string $detail): self
    {
        return new self(GatewayPaymentState::NotPaid, null, null, $gatewayStatus, $detail);
    }

    public static function refunded(?string $gatewayStatus, string $detail): self
    {
        return new self(GatewayPaymentState::Refunded, null, null, $gatewayStatus, $detail);
    }

    public static function ambiguous(?string $gatewayStatus, string $detail): self
    {
        return new self(GatewayPaymentState::Ambiguous, null, null, $gatewayStatus, $detail);
    }

    public static function unreachable(string $detail): self
    {
        return new self(GatewayPaymentState::Unreachable, null, null, null, $detail);
    }
}
