<?php

declare(strict_types=1);

namespace App\Modules\X211\Domain;

use App\Modules\X121\Models\Conversation;
use App\Modules\X121\Models\Message;
use App\Modules\X199\Domain\InvoiceReader;
use App\Modules\X211\Events\ArEscalatedToHuman;
use App\Modules\X211\Events\ArFeeApplied;
use App\Modules\X211\Events\ArLateFeeTermSet;
use App\Modules\X211\Events\ArPackaged;
use App\Modules\X211\Events\ArPlanAccepted;
use App\Modules\X211\Models\ArCollectionsPackage;
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
     * Apply a late fee inside the agreement's term — refused when the agreement names none (G1-71); the percent and the cap are the tenant's row (P-193).
     */
    public function applyLateFee(int $businessId, int $invoiceId, int $feeCents): array
    {
        return DB::transaction(function () use ($businessId, $invoiceId, $feeCents) {
            $invoice = app(InvoiceReader::class)->forBusiness($businessId, $invoiceId);

            // G1-71: a fee with no matching TERM in the agreement is refused — the term is the tenant's
            // ar_plan_terms row (P-193: a ROW, never a literal), null means the agreement names no late fee.
            // The row is READ here, never created: a refusal writes nothing (M29-C).
            $terms = ArPlanTerm::where('business_id', $businessId)->first();
            if ($terms === null || $terms->late_fee_percent === null) {
                throw new FeeWithoutTermException(sprintf(
                    'No late-fee term in the agreement for %s: a fee with no matching term is refused. Nothing was applied.',
                    $invoice->invoice_number
                ));
            }

            $percentCap = intdiv($invoice->total_cents * $terms->late_fee_percent, 100);
            $maxFee = $terms->late_fee_cap_cents === null ? $percentCap : min($percentCap, $terms->late_fee_cap_cents);
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
     * Write the agreement's late-fee term — the percent and the cap are the tenant's row (P-193); a fee is
     * applied only inside it (G1-71). The cap is optional; the percent is not.
     */
    public function setLateFeeTerm(int $businessId, int $percent, ?int $capCents): ArPlanTerm
    {
        if ($percent < 1 || $percent > 100) {
            throw new \InvalidArgumentException('A late fee is 1 to 100 percent of the invoice.');
        }
        if ($capCents !== null && $capCents < 1) {
            throw new \InvalidArgumentException('The cap is an amount in cents, or none.');
        }

        return DB::transaction(function () use ($businessId, $percent, $capCents) {
            $terms = ArPlanTerm::updateOrCreate(
                ['business_id' => $businessId],
                ['late_fee_percent' => $percent, 'late_fee_cap_cents' => $capCents]
            );

            Event::dispatch(new ArLateFeeTermSet($businessId, $percent, $capCents));

            return $terms;
        });
    }

    /**
     * Offer and accept structured installment payment plan.
     */
    public function offerPlan(int $businessId, int $invoiceId, int $installmentsCount = 3, string $frequency = 'monthly'): PaymentPlan
    {
        return DB::transaction(function () use ($businessId, $invoiceId, $installmentsCount, $frequency) {
            $invoice = app(InvoiceReader::class)->forBusiness($businessId, $invoiceId);

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
        ?string $reference = null,
        ?string $photoPath = null
    ): OfflinePayment {
        return DB::transaction(function () use ($businessId, $invoiceId, $amountCents, $method, $reference, $photoPath) {
            // G1-74 / N-033: a logged offline payment carries a reference or a photo, or it is refused —
            // an unreferenced row cannot be reconciled against the deposit. The throw is before the first write.
            if (trim((string) $reference) === '' && trim((string) $photoPath) === '') {
                $invoice = app(InvoiceReader::class)->forBusiness($businessId, $invoiceId);
                throw new UnreferencedPaymentException("Payment for {$invoice->invoice_number} must have a reference or photo. Nothing was logged.");
            }

            $payment = OfflinePayment::create([
                'business_id' => $businessId,
                'invoice_id' => $invoiceId,
                'amount_cents' => $amountCents,
                'payment_method' => $method,
                'reference_number' => $reference,
                'photo_path' => $photoPath,
            ]);

            $invoice = app(InvoiceReader::class)->forBusiness($businessId, $invoiceId);
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
     * Package a defaulted account into the collections evidence bundle.
     *
     * G1-65: the bundle is BUILT here; transmission to an agency is a human
     * action (the principal is recorded on the row). R211: a resolution attempt
     * — a reason, a plan or a payment — is recorded FIRST, or nothing is packaged.
     */
    public function packageForCollections(int $businessId, int $invoiceId, ?int $packagedByUserId = null): array
    {
        return DB::transaction(function () use ($businessId, $invoiceId, $packagedByUserId) {
            $invoice = app(InvoiceReader::class)->forBusiness($businessId, $invoiceId);

            $attempted = ArDunningAction::where('business_id', $businessId)->where('invoice_id', $invoiceId)->exists()
                || PaymentPlan::where('business_id', $businessId)->where('invoice_id', $invoiceId)->exists()
                || OfflinePayment::where('business_id', $businessId)->where('invoice_id', $invoiceId)->exists();

            if (! $attempted) {
                throw new NoResolutionAttemptException(
                    "Record a resolution attempt first — a reason, a plan or a payment — before {$invoice->invoice_number} goes to collections. Nothing was packaged."
                );
            }

            $conversationIds = $invoice->customer_id
                ? Conversation::where('business_id', $businessId)->where('person_id', $invoice->customer_id)->pluck('id')
                : collect();

            $contents = [
                'invoice_number' => $invoice->invoice_number,
                'total_cents' => (int) $invoice->total_cents,
                'paid_cents' => (int) $invoice->paid_cents,
                'balance_cents' => (int) ($invoice->total_cents - $invoice->paid_cents),
                'due_date' => $invoice->due_date->toDateString(),
                'lines' => app(InvoiceReader::class)->linesForInvoice($businessId, $invoiceId),
                'payments' => OfflinePayment::where('business_id', $businessId)->where('invoice_id', $invoiceId)->orderBy('id')
                    ->get(['amount_cents', 'payment_method', 'reference_number', 'created_at'])->toArray(),
                'actions' => ArDunningAction::where('business_id', $businessId)->where('invoice_id', $invoiceId)->orderBy('id')
                    ->get(['action', 'reason', 'created_at'])->toArray(),
                'messages_count' => Message::where('business_id', $businessId)->whereIn('conversation_id', $conversationIds)->count(),
            ];

            $package = ArCollectionsPackage::create([
                'business_id' => $businessId,
                'invoice_id' => $invoiceId,
                'packaged_by_user_id' => $packagedByUserId,
                'contents' => $contents,
            ]);

            $state = ReceivableState::firstOrCreate(
                ['business_id' => $businessId, 'invoice_id' => $invoiceId],
                ['status' => 'packaged_collections']
            );
            $state->update(['status' => 'packaged_collections']);

            Event::dispatch(new ArPackaged($businessId, $invoiceId));

            return [
                'invoice_id' => $invoiceId,
                'status' => 'packaged_collections',
                'bundle_url' => null,
                'package_id' => $package->id,
                'packaged_by_user_id' => $packagedByUserId,
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
            app(InvoiceReader::class)->forBusiness($businessId, $invoiceId);

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
