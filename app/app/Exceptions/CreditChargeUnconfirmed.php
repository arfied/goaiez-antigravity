<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\CreditPurchase;
use App\Services\Billing\CreditTopUps;
use App\Services\Billing\PurchaseReconciliation;
use RuntimeException;

/**
 * The card may have been charged and we do not know.
 *
 * ⛔ **THIS IS NOT A FAILURE AND MUST NEVER BE HANDLED AS ONE.** It is the state
 * where the request reached the gateway and the answer did not come back — a
 * connection timeout, or a body we could not read. The money may have moved; the
 * only honest thing anybody can say about it is that nobody knows yet.
 *
 * ⚠️ **IT EXISTS BECAUSE THE OPPOSITE WAS SHIPPED.** `CreditTopUps` recorded a
 * lost response as `failed`, which is terminal: the purchase left the
 * `pending`/`authorized` set {@see PurchaseReconciliation} scans, so nothing ever
 * revisited it, and the standing arrangement above it saw no charge, counted
 * nothing against the monthly limit and charged again the next night. Three lost
 * responses were $150 taken from an account whose agreement said $50.
 *
 * ⛔ **THE PURCHASE TRAVELS WITH THE EXCEPTION, AND THAT IS THE WHOLE POINT.**
 * The row is left exactly where {@see CreditTopUps::chargeStoredCard()} opened it
 * — `pending`, with no transaction id — so the reconciliation sweep still owns
 * it. A caller that needs to count the money it may have spent needs the row, and
 * asking the database for "the purchase I just opened" is the guess this hands
 * over instead.
 *
 * ⚠️ **WHAT THE SWEEP CAN AND CANNOT DO WITH IT** (3603): Authorize.Net's
 * per-transaction read needs a `transId` and this purchase has none, so the sweep
 * will examine it, be unable to answer, and abandon it loudly with a warning
 * naming it. **That is the designed outcome** — a person looking in the merchant
 * interface — and it is strictly better than the silence a `failed` row produced.
 */
final class CreditChargeUnconfirmed extends RuntimeException
{
    private function __construct(
        public readonly CreditPurchase $purchase,
        public readonly string $reason,
    ) {
        parent::__construct("A credit charge was sent and not answered: {$reason}");
    }

    /**
     * @param  string  $reason  A vendor error code or a fixed transport label,
     *                          never a vendor message: those quote the value they
     *                          rejected, including a card's last four.
     */
    public static function of(CreditPurchase $purchase, string $reason): self
    {
        return new self($purchase, $reason);
    }
}
