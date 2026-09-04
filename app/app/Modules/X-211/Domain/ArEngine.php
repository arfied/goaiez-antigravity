<?php

declare(strict_types=1);

namespace App\Modules\X211\Domain;

use App\Modules\X199\Models\Invoice;
use App\Modules\X211\Events\ArEscalatedToHuman;
use App\Modules\X211\Events\ArFeeApplied;
use App\Modules\X211\Events\ArPackaged;
use App\Modules\X211\Events\ArPlanAccepted;
use App\Modules\X211\Models\ArDunningAction;
use App\Modules\X211\Models\ArPlanTerm;
use App\Modules\X211\Models\OfflinePayment;
use App\Modules\X211\Models\PaymentPlan;
use App\Modules\X211\Models\ReceivableState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class ArEngine
{
    /**
     * §216.3 THE REASON RULE — dunning is decided by WHY, not by DAYS. The
     * four reasons the plan names, plus the promise the ageing screen already
     * groups on. Only silence is automatable; two of these need a human NOW.
     */
    public const REASONS = [
        'card_expired' => 'Card expired',
        'disputed_line' => 'Disputed line item',
        'complaint' => 'Complaint on the thread',
        'promised' => 'Customer promised to pay',
        'silence' => 'No reply yet',
    ];

    /** N-033: an open RECOVER blocks dunning entirely — these route to a human, immediately. */
    public const NEEDS_HUMAN = ['disputed_line', 'complaint'];

    /**
     * Apply late fee with standard legal capping (max 10% or $50).
     */
    public function applyLateFee(int $businessId, int $invoiceId, int $feeCents): array
    {
        return DB::transaction(function () use ($businessId, $invoiceId, $feeCents) {
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
    public function offerPlan(int $businessId, int $invoiceId, int $installmentsCount = 3, string $frequency = 'monthly'): PaymentPlan
    {
        return DB::transaction(function () use ($businessId, $invoiceId, $installmentsCount, $frequency) {
            $invoice = Invoice::where('business_id', $businessId)->findOrFail($invoiceId);

            if ($installmentsCount < 2) {
                throw new \InvalidArgumentException('A plan is at least two payments.');
            }

            $daysPer = ['weekly' => 7, 'biweekly' => 14, 'monthly' => 30][$frequency] ?? null;
            if ($daysPer === null) {
                throw new \InvalidArgumentException("Unknown frequency {$frequency}.");
            }

            // G1-61 / G1-70 / N-033: past the threshold this is credit, not a schedule — it routes to a
            // financing partner and we never hold the paper. The throw is before the first write.
            $terms = ArPlanTerm::firstOrCreate(['business_id' => $businessId]);
            $termDays = $installmentsCount * $daysPer;
            if ($installmentsCount > $terms->max_installments || $termDays > $terms->max_term_days) {
                throw new PlanPastThresholdException(sprintf(
                    '%d %s payments over %d days is credit, not a schedule: past %d payments or %d days this routes to a financing partner. Nothing was stored.',
                    $installmentsCount, $frequency, $termDays, $terms->max_installments, $terms->max_term_days
                ));
            }

            $remaining = $invoice->total_cents - $invoice->paid_cents;
            $installmentAmount = (int) ceil($remaining / $installmentsCount);

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
        ?string $reference = null
    ): OfflinePayment {
        return DB::transaction(function () use ($businessId, $invoiceId, $amountCents, $method, $reference) {
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
    public function packageForCollections(int $businessId, int $invoiceId): array
    {
        return DB::transaction(function () use ($businessId, $invoiceId) {
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

    /**
     * Record WHY an invoice is unpaid. A reason that needs a human escalates —
     * the escalate_to_human row is what the ageing screen and the dunning
     * listener both read, so it is a gate, not a sort (§216.5 FAILS IF).
     */
    public function recordReason(int $businessId, int $invoiceId, string $reasonCode): ArDunningAction
    {
        $label = self::REASONS[$reasonCode] ?? null;
        if ($label === null) {
            throw new \InvalidArgumentException("Unknown reason {$reasonCode}.");
        }

        return DB::transaction(function () use ($businessId, $invoiceId, $reasonCode, $label) {
            Invoice::where('business_id', $businessId)->findOrFail($invoiceId);

            $recorded = ArDunningAction::create([
                'business_id' => $businessId,
                'invoice_id' => $invoiceId,
                'action' => 'reason_recorded',
                'reason' => $label,
            ]);

            if (in_array($reasonCode, self::NEEDS_HUMAN, true)) {
                $escalation = ArDunningAction::firstOrCreate(
                    ['business_id' => $businessId, 'invoice_id' => $invoiceId, 'action' => 'escalate_to_human'],
                    ['reason' => $label]
                );

                $state = ReceivableState::firstOrCreate(
                    ['business_id' => $businessId, 'invoice_id' => $invoiceId],
                    ['status' => 'escalated']
                );
                $state->update(['status' => 'escalated']);

                if ($escalation->wasRecentlyCreated) {
                    Event::dispatch(new ArEscalatedToHuman($businessId, $invoiceId, $label));
                }
            }

            return $recorded;
        });
    }
}
