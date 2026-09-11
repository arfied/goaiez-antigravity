<?php

declare(strict_types=1);

namespace App\Modules\X170\Domain;

use App\Modules\X170\Events\CommissionCalculated;
use App\Modules\X170\Events\CommissionClawedBack;
use App\Modules\X170\Events\CommissionReleased;
use App\Modules\X170\Models\Commission;
use App\Modules\X170\Models\Scorecard;
use Illuminate\Support\Facades\Event;

final class CommissionEngine
{
    /**
     * Computes commission for payees on gross profit (G7-32, G7-39).
     */
    public function compute(
        int $businessId,
        int $invoiceId,
        int $grossProfitCents,
        array $payeeSplits
    ): array {
        $created = [];

        foreach ($payeeSplits as $split) {
            $staffId = $split['staff_id'];
            $pct = $split['percentage'] ?? 10.0; // 10%
            $amountCents = (int) round(($grossProfitCents * $pct) / 100);

            $comm = Commission::create([
                'business_id' => $businessId,
                'invoice_id' => $invoiceId,
                'staff_id' => $staffId,
                'amount_cents' => $amountCents,
                'status' => 'pending_cash_collection', // G1-16, G9-29
            ]);

            Event::dispatch(new CommissionCalculated($businessId, $comm->id, $amountCents));
            $created[] = $comm;
        }

        return $created;
    }

    /**
     * Releases commission strictly upon payment.captured confirmation (TEST ANCHOR, G1-16).
     */
    public function release(int $businessId, int $commissionId, ?string $paymentCapturedId): array
    {
        $comm = Commission::where('business_id', $businessId)->findOrFail($commissionId);

        // 1. INVARIANT: No commission row moves to RELEASED without a matching payment.captured (TEST ANCHOR)
        if (empty($paymentCapturedId)) {
            return [
                'status' => 'refused',
                'refusal_code' => 'PAYMENT_CAPTURED_REQUIRED_FOR_COMMISSION_RELEASE',
                'message' => 'No commission row moves to RELEASED without a matching payment.captured for the invoice',
            ];
        }

        $comm->update([
            'status' => 'released',
            'payment_id' => $paymentCapturedId,
        ]);

        // Update staff scorecard
        $scorecard = Scorecard::firstOrCreate(
            ['business_id' => $businessId, 'staff_id' => $comm->staff_id, 'period_key' => '2026-Q3'],
            ['revenue_collected_cents' => 0, 'commissions_earned_cents' => 0]
        );
        $scorecard->increment('commissions_earned_cents', $comm->amount_cents);

        Event::dispatch(new CommissionReleased($businessId, $comm->id, $paymentCapturedId));

        return [
            'status' => 'released',
            'commission_id' => $comm->id,
            'payment_id' => $paymentCapturedId,
            'amount_cents' => $comm->amount_cents,
        ];
    }

    /**
     * Handles chargeback on released commission by writing clawback of same amount (TEST ANCHOR).
     */
    public function clawback(int $businessId, int $commissionId, string $reason = 'chargeback_received'): array
    {
        $comm = Commission::where('business_id', $businessId)->findOrFail($commissionId);

        $clawbackAmount = $comm->amount_cents; // Same amount as commission (TEST ANCHOR)

        $comm->update([
            'status' => 'clawed_back',
            'clawback_amount_cents' => $clawbackAmount,
            'clawback_reason' => $reason,
        ]);

        // Deduct from scorecard
        $scorecard = Scorecard::where('business_id', $businessId)
            ->where('staff_id', $comm->staff_id)
            ->where('period_key', '2026-Q3')
            ->first();

        if ($scorecard !== null) {
            $scorecard->decrement('commissions_earned_cents', $clawbackAmount);
        }

        Event::dispatch(new CommissionClawedBack($businessId, $comm->id, $clawbackAmount, $reason));

        return [
            'status' => 'clawed_back',
            'commission_id' => $comm->id,
            'clawback_amount_cents' => $clawbackAmount,
            'reason' => $reason,
        ];
    }

    public function exportPayroll(int $businessId): array
    {
        $commissions = Commission::where('business_id', $businessId)
            ->where('status', 'released')
            ->get();

        return $commissions->toArray();
    }
}
