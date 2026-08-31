<?php

declare(strict_types=1);

namespace App\Modules\X198\Domain;

use App\Modules\X198\Events\PaymentCaptured;
use App\Modules\X198\Events\PayoutReconciled;
use App\Modules\X198\Events\ReconciliationDiscrepancy;
use App\Modules\X198\Models\MerchantConnection;
use App\Modules\X198\Models\Payment;
use App\Modules\X198\Models\Payout;
use App\Modules\X198\Models\ReconciliationRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class GatewayEngine
{
    /**
     * Connect merchant gateway account.
     */
    public function connect(int $businessId, string $gatewayName, string $merchantAccountId): MerchantConnection
    {
        return MerchantConnection::updateOrCreate(
            ['business_id' => $businessId, 'gateway_name' => $gatewayName],
            ['merchant_account_id' => $merchantAccountId, 'is_connected' => true]
        );
    }

    /**
     * Idempotent payment capture (TEST ANCHOR, G1-23, G1-34, G17-04).
     * Only accepts payment tokens; never touches raw credentials.
     */
    public function capture(
        int $businessId,
        int $amountCents,
        string $paymentToken,
        string $idempotencyKey,
        string $currency = 'USD'
    ): Payment {
        return DB::transaction(function () use ($businessId, $amountCents, $paymentToken, $idempotencyKey, $currency) {
            // Idempotency check: duplicated ref charges once (G17-04, G1-23)
            $existing = Payment::where('business_id', $businessId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $connection = MerchantConnection::where('business_id', $businessId)->first();

            if ($connection === null || ! $connection->is_connected) {
                throw new \InvalidArgumentException('Gateway connection is absent; payment capture refused before external request');
            }

            $payment = Payment::create([
                'business_id' => $businessId,
                'merchant_connection_id' => $connection->id,
                'gateway_charge_id' => null,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'payment_token' => $paymentToken,
                'idempotency_key' => $idempotencyKey,
                'status' => 'pending',
            ]);

            Event::dispatch(new PaymentCaptured(
                businessId: $businessId,
                paymentId: $payment->id,
                gatewayChargeId: $payment->gateway_charge_id,
                amountCents: $amountCents
            ));

            return $payment;
        });
    }

    /**
     * Reconcile payout and log immutable discrepancy if mismatched (TEST ANCHOR).
     */
    public function reconcilePayout(int $businessId, int $payoutId, int $expectedCents, int $actualCents): array
    {
        return DB::transaction(function () use ($businessId, $payoutId, $expectedCents, $actualCents) {
            $payout = Payout::where('business_id', $businessId)->findOrFail($payoutId);

            $discrepancy = $actualCents - $expectedCents;
            $status = ($discrepancy === 0) ? 'balanced' : 'discrepancy_logged';

            $run = ReconciliationRun::create([
                'business_id' => $businessId,
                'payout_id' => $payoutId,
                'expected_cents' => $expectedCents,
                'actual_cents' => $actualCents,
                'discrepancy_cents' => $discrepancy,
                'discrepancy_reason' => ($discrepancy !== 0) ? "Mismatched payout: expected {$expectedCents}, got {$actualCents}" : null,
                'status' => $status,
            ]);

            if ($discrepancy !== 0) {
                $payout->update(['status' => 'discrepancy']);
                Event::dispatch(new ReconciliationDiscrepancy(
                    businessId: $businessId,
                    runId: $run->id,
                    discrepancyCents: $discrepancy,
                    reason: $run->discrepancy_reason
                ));
            } else {
                $payout->update(['status' => 'reconciled']);
                Event::dispatch(new PayoutReconciled(
                    businessId: $businessId,
                    payoutId: $payoutId,
                    amountCents: $actualCents
                ));
            }

            return [
                'payout_id' => $payoutId,
                'run_id' => $run->id,
                'discrepancy_cents' => $discrepancy,
                'status' => $status,
            ];
        });
    }
}
