<?php

declare(strict_types=1);

namespace App\Modules\X211\Domain;

use App\Modules\X199\Models\Invoice;
use App\Modules\X211\Events\ArFeeApplied;
use App\Modules\X211\Events\ArPackaged;
use App\Modules\X211\Events\ArPlanAccepted;
use App\Modules\X211\Models\OfflinePayment;
use App\Modules\X211\Models\PaymentPlan;
use App\Modules\X211\Models\ReceivableState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class ArEngine
{
    /**
     * Apply late fee with standard legal capping (max 10% or $50).
     */
    public function applyLateFee(int $businessId, int $invoiceId, int $feeCents, bool $hasTerm = true): array
    {
        return DB::transaction(function () use ($businessId, $invoiceId, $feeCents, $hasTerm) {
            if (!$hasTerm) {
                throw new \DomainException('A fee with no matching TERM in the agreement is refused');
            }
            $invoice = Invoice::where('business_id', $businessId)->findOrFail($invoiceId);
            $maxFee = min((int) ($invoice->total_cents * 0.10), 5000); // capped at 10% or $50
            $finalFee = min($feeCents, $maxFee);

            $state = ReceivableState::firstOrCreate(
                ['business_id' => $businessId, 'invoice_id' => $invoiceId],
                ['status' => 'overdue']
            );

            $state->update([
                'late_fee_cents' => $state->late_fee_cents + $finalFee,
                'status' => 'overdue',
            ]);

            Event::dispatch(new ArFeeApplied($businessId, $invoiceId, $finalFee));

            return [
                'invoice_id' => $invoiceId,
                'applied_fee_cents' => $finalFee,
                'total_late_fee_cents' => $state->late_fee_cents,
            ];
        });
    }

    /**
     * Offer and accept structured installment payment plan.
     */
    public function offerPlan(int $businessId, int $invoiceId, int $installmentsCount = 3, string $frequency = 'monthly', int $threshold = 100000): PaymentPlan
    {
        return DB::transaction(function () use ($businessId, $invoiceId, $installmentsCount, $frequency, $threshold) {
            $invoice = Invoice::where('business_id', $businessId)->findOrFail($invoiceId);
            $remaining = $invoice->total_cents - $invoice->paid_cents;
            $installmentAmount = (int) ceil($remaining / $installmentsCount);
            if ($remaining > $threshold) {
                throw new \DomainException('A plan past the threshold routes to a financing partner');
            }

            $plan = PaymentPlan::create([
                'business_id' => $businessId,
                'invoice_id' => $invoiceId,
                'installments_count' => $installmentsCount,
                'installment_amount_cents' => $installmentAmount,
                'frequency' => $frequency,
                'status' => 'accepted',
            ]);

            $state = ReceivableState::firstOrCreate(
                ['business_id' => $businessId, 'invoice_id' => $invoiceId],
                ['status' => 'payment_plan']
            );
            $state->update(['status' => 'payment_plan']);

            Event::dispatch(new ArPlanAccepted($businessId, $plan->id, $invoiceId));

            return $plan;
        });
    }

    /**
     * Log offline payment and reconcile receivable.
     */
    public function logOfflinePayment(
        int $businessId,
        int $invoiceId,
        int $amountCents,
        string $method = 'check',
        ?string $reference = null,
        ?string $photoUrl = null
    ): OfflinePayment {
        return DB::transaction(function () use ($businessId, $invoiceId, $amountCents, $method, $reference, $photoUrl) {
            if (empty($reference) && empty($photoUrl)) {
                throw new \DomainException('Offline payment needs a reference or a photo');
            }
            $payment = OfflinePayment::create([
                'business_id' => $businessId,
                'invoice_id' => $invoiceId,
                'amount_cents' => $amountCents,
                'payment_method' => $method,
                'reference_number' => $reference,
            ]);

            $invoice = Invoice::where('business_id', $businessId)->findOrFail($invoiceId);
            $newPaid = $invoice->paid_cents + $amountCents;
            $status = ($newPaid >= $invoice->total_cents) ? 'paid' : 'issued';
            $invoice->update(['paid_cents' => $newPaid, 'status' => $status]);

            if ($status === 'paid') {
                ReceivableState::where('business_id', $businessId)
                    ->where('invoice_id', $invoiceId)
                    ->update(['status' => 'current']);
            }

            return $payment;
        });
    }

    /**
     * Package defaulted account into collections evidence bundle.
     */
    public function packageForCollections(int $businessId, int $invoiceId, bool $isHumanAction = false): array
    {
        return DB::transaction(function () use ($businessId, $invoiceId, $isHumanAction) {
            if (!$isHumanAction) {
                throw new \DomainException('Collections transmission is a human action only');
            }
            $invoice = Invoice::where('business_id', $businessId)->findOrFail($invoiceId);
            $bundleUrl = "https://cdn.goaiez.com/collections/bundle_{$invoice->invoice_number}.zip";

            $state = ReceivableState::firstOrCreate(
                ['business_id' => $businessId, 'invoice_id' => $invoiceId],
                ['status' => 'packaged_collections']
            );
            $state->update(['status' => 'packaged_collections']);

            Event::dispatch(new ArPackaged($businessId, $invoiceId, $bundleUrl));

            return [
                'invoice_id' => $invoiceId,
                'status' => 'packaged_collections',
                'bundle_url' => $bundleUrl,
            ];
        });
    }
}
