<?php

declare(strict_types=1);

namespace App\Modules\X198\Domain;

use App\Modules\X198\Events\MerchantApplied;
use App\Modules\X198\Events\PaymentCaptured;
use App\Modules\X198\Events\PayoutReconciled;
use App\Modules\X198\Events\ReconciliationDiscrepancy;
use App\Modules\X198\Models\MerchantConnection;
use App\Modules\X198\Models\Payment;
use App\Modules\X198\Models\Payout;
use App\Modules\X198\Models\ReconciliationRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * payments.status vocabulary:
 * - captured — a non-null gateway charge id came back (R245, MONEY-61)
 * - awaiting_processor — no adapter was asked; also the column default (R245, MONEY-61)
 * - failed — the gateway was reached and refused (R245, MONEY-60)
 * - refunded / chargeback — declared in the original migration's :35 comment, written by no code today
 * - a missing key writes nothing (R245, MONEY-62)
 */
final class GatewayEngine
{
    public function applyForSubMerchant(int $businessId, int $connectionId): array
    {
        $connection = MerchantConnection::where('business_id', $businessId)->findOrFail($connectionId);

        if (($connection->merchant_status ?? 'external_gateway') !== 'external_gateway') {
            return ['status' => 'refused', 'refusal_code' => 'MERCHANT_STATUS_NOT_EXTERNAL', 'message' => 'Status is not external'];
        }

        if (! app()->bound(ProcessorAdapter::class)) {
            return ['status' => 'refused', 'refusal_code' => 'PROCESSOR_ADAPTER_ABSENT', 'message' => 'No adapter bound'];
        }

        $adapter = app(ProcessorAdapter::class);
        $applicationRef = $adapter->beginKyc($businessId);

        $connection->update([
            'merchant_status' => 'pending_kyc',
            'merchant_relationship' => 'sub_merchant',
        ]);

        Event::dispatch(new MerchantApplied($businessId, $connection->id, $applicationRef));

        return ['status' => 'applied', 'application_ref' => $applicationRef];
    }

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
        try {
            return DB::transaction(function () use ($businessId, $amountCents, $paymentToken, $idempotencyKey, $currency) {
                // Idempotency check: duplicated ref charges once (G17-04, G1-23)
                $existing = Payment::where('business_id', $businessId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->where('status', '!=', 'failed')
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }

                $connection = MerchantConnection::where('business_id', $businessId)->first();

                if ($connection === null || ! $connection->is_connected) {
                    throw new \InvalidArgumentException('Gateway connection is absent; payment capture refused before external request');
                }

                $gatewayChargeId = null;
                if ($connection->gateway_name === 'stripe') {
                    $gatewayChargeId = app(StripeGatewayClient::class)->charge($amountCents, $paymentToken, $currency);
                }

                $status = $gatewayChargeId !== null ? 'captured' : 'awaiting_processor';

                $payment = Payment::create([
                    'business_id' => $businessId,
                    'merchant_connection_id' => $connection->id,
                    'gateway_charge_id' => $gatewayChargeId,
                    'amount_cents' => $amountCents,
                    'currency' => $currency,
                    'payment_token' => $paymentToken,
                    'idempotency_key' => $idempotencyKey,
                    'status' => $status,
                ]);

                Event::dispatch(new PaymentCaptured(
                    businessId: $businessId,
                    paymentId: $payment->id,
                    gatewayChargeId: $payment->gateway_charge_id,
                    amountCents: $amountCents
                ));

                return $payment;
            });
        } catch (GatewayNotConfiguredException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            DB::transaction(function () use ($businessId, $amountCents, $paymentToken, $idempotencyKey, $currency) {
                $connection = MerchantConnection::where('business_id', $businessId)->first();
                Payment::create([
                    'business_id' => $businessId,
                    'merchant_connection_id' => $connection?->id,
                    'gateway_charge_id' => null,
                    'amount_cents' => $amountCents,
                    'currency' => $currency,
                    'payment_token' => $paymentToken,
                    'idempotency_key' => $idempotencyKey,
                    'status' => 'failed',
                ]);
            });

            throw $e;
        }
    }

    /**
     * Reconcile payout and log immutable discrepancy if mismatched (TEST ANCHOR).
     * The actual side is the payout row's own figure and never a caller's.
     */
    public function reconcilePayout(int $businessId, int $payoutId, int $expectedCents): array
    {
        return DB::transaction(function () use ($businessId, $payoutId, $expectedCents) {
            $payout = Payout::where('business_id', $businessId)->findOrFail($payoutId);
            $actualCents = (int) $payout->amount_cents;

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

    /**
     * A discrepancy is reviewed, never corrected (X-198 anchor): the mark is who
     * looked and when; the numbers on the run are never touched.
     */
    public function reviewDiscrepancy(int $businessId, int $runId, int $userId): ReconciliationRun
    {
        $run = ReconciliationRun::where('business_id', $businessId)->findOrFail($runId);

        if ($run->status !== 'discrepancy_logged') {
            throw new NothingToReviewException('Nothing to review: that payout balanced to the cent.');
        }

        if ($run->reviewed_at !== null) {
            return $run;
        }

        $run->update(['reviewed_at' => now(), 'reviewed_by_user_id' => $userId]);

        return $run->fresh();
    }

    /**
     * A detached payment (its connection row is gone) is attached to one of THIS
     * account's connections, once. A payment that already lands somewhere is never
     * moved — that would be a payment appearing in a payout it was not in (X-198 anchor).
     */
    public function attachPayment(int $businessId, int $paymentId, int $connectionId): Payment
    {
        return DB::transaction(function () use ($businessId, $paymentId, $connectionId) {
            $payment = Payment::where('business_id', $businessId)->findOrFail($paymentId);
            $connection = MerchantConnection::where('business_id', $businessId)->findOrFail($connectionId);

            if ($payment->merchant_connection_id !== null) {
                $current = MerchantConnection::where('business_id', $businessId)->find($payment->merchant_connection_id);
                throw new PaymentAlreadyLandedException(sprintf(
                    'That payment already lands in %s; a payment is never moved.',
                    $current->merchant_account_id ?? 'connection #'.$payment->merchant_connection_id
                ));
            }

            $payment->update(['merchant_connection_id' => $connection->id]);

            return $payment->fresh();
        });
    }
}
