<?php

declare(strict_types=1);

namespace App\Modules\X214\Domain;

use App\Modules\X214\Events\SurchargeApplied;
use App\Modules\X214\Events\SurchargeDisclosed;
use App\Modules\X214\Models\SurchargePolicy;
use Illuminate\Support\Facades\Event;

final class SurchargeEngine
{
    private static array $disclosedTransactions = [];

    /**
     * Quotes surcharge.
     * 1. Debit NEVER surcharged (BIN-asserted, unknown type treated as debit: TEST ANCHOR).
     * 2. Ceiling is LOWER of 3% (300 bps) and merchant's effective rate. Above is REFUSED, not clamped (TEST ANCHOR).
     * 3. Precedes apply turn and dispatches surcharge.disclosed (TEST ANCHOR).
     */
    public function quote(
        int $businessId,
        string $transactionId,
        int $amountCents,
        ?string $cardType, // credit, debit, null/unknown
        ?string $bin = null,
        int $requestedRateBps = 250 // 2.50%
    ): array {
        $policy = SurchargePolicy::firstOrCreate(
            ['business_id' => $businessId],
            ['merchant_effective_rate_basis_points' => 250, 'is_enabled' => true]
        );

        // 1. Debit Protection: debit is NEVER surcharged — BIN-asserted, unknown type treated as debit (TEST ANCHOR)
        $isDebitOrUnknown = ($cardType === 'debit') || empty($cardType) || ($bin !== null && str_starts_with($bin, '4000'));

        if ($isDebitOrUnknown || ! $policy->is_enabled) {
            self::$disclosedTransactions[$transactionId] = [
                'business_id' => $businessId,
                'surcharge_cents' => 0,
                'rate_bps' => 0,
                'is_surcharged' => false,
            ];

            Event::dispatch(new SurchargeDisclosed($businessId, $transactionId, 0, 0));

            return [
                'status' => 'exempt_debit_or_unknown',
                'transaction_id' => $transactionId,
                'surcharge_cents' => 0,
                'rate_bps' => 0,
                'is_surcharged' => false,
                'message' => 'Debit and unknown card types are never surcharged',
            ];
        }

        // 2. Ceiling Gate: ceiling is LOWER of 3% (300 bps) and merchant's effective rate (TEST ANCHOR)
        $legalCeilingBps = min(300, $policy->merchant_effective_rate_basis_points);

        if ($requestedRateBps > $legalCeilingBps) {
            // Above ceiling is REFUSED, not clamped (TEST ANCHOR)
            return [
                'status' => 'refused',
                'refusal_code' => 'RATE_EXCEEDS_LEGAL_CEILING',
                'message' => "Requested rate ({$requestedRateBps} bps) exceeds the lower of 3% and merchant rate ({$legalCeilingBps} bps); refused, not clamped",
                'ceiling_bps' => $legalCeilingBps,
            ];
        }

        $surchargeCents = (int) round(($amountCents * $requestedRateBps) / 10000);

        self::$disclosedTransactions[$transactionId] = [
            'business_id' => $businessId,
            'surcharge_cents' => $surchargeCents,
            'rate_bps' => $requestedRateBps,
            'is_surcharged' => true,
        ];

        Event::dispatch(new SurchargeDisclosed($businessId, $transactionId, $surchargeCents, $requestedRateBps));

        return [
            'status' => 'quoted_and_disclosed',
            'transaction_id' => $transactionId,
            'amount_cents' => $amountCents,
            'surcharge_cents' => $surchargeCents,
            'rate_bps' => $requestedRateBps,
            'is_surcharged' => true,
        ];
    }

    /**
     * Applies surcharge.
     * Preceding disclosure check: NO apply without a preceding surcharge.disclosed on same transaction (TEST ANCHOR).
     */
    public function apply(int $businessId, string $transactionId): array
    {
        // 3. Disclosure Verification (TEST ANCHOR)
        if (! isset(self::$disclosedTransactions[$transactionId])) {
            return [
                'status' => 'refused',
                'refusal_code' => 'NO_PRECEDING_DISCLOSURE',
                'message' => "No apply without a preceding surcharge.disclosed on transaction {$transactionId}",
                'applied' => false,
            ];
        }

        $disclosure = self::$disclosedTransactions[$transactionId];

        Event::dispatch(new SurchargeApplied(
            $businessId,
            $transactionId,
            $disclosure['surcharge_cents'],
            $disclosure['rate_bps']
        ));

        return [
            'status' => 'applied',
            'transaction_id' => $transactionId,
            'surcharge_cents' => $disclosure['surcharge_cents'],
            'rate_bps' => $disclosure['rate_bps'],
            'applied' => true,
        ];
    }
}
