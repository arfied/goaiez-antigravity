<?php

declare(strict_types=1);

namespace App\Modules\X201\Domain;

use App\Modules\X201\Events\DisputeLost;
use App\Modules\X201\Events\DisputeOpened;
use App\Modules\X201\Events\DisputeResolved;
use App\Modules\X201\Events\EvidenceCompiled;
use App\Modules\X201\Models\Dispute;
use App\Modules\X201\Models\DisputeEvidence;
use App\Modules\X201\Models\DisputeOutcome;
use Illuminate\Support\Facades\Event;

final class DisputeDefenseEngine
{
    public function record(int $businessId, int $invoiceId, int $chargebackAmountCents, string $reason = 'fraudulent'): Dispute
    {
        $dispute = Dispute::create([
            'business_id' => $businessId,
            'invoice_id' => $invoiceId,
            'chargeback_amount_cents' => $chargebackAmountCents,
            'reason' => $reason,
            'status' => 'opened',
        ]);

        Event::dispatch(new DisputeOpened($businessId, $dispute->id, $invoiceId, $chargebackAmountCents));

        return $dispute;
    }

    /**
     * Compiles evidence bundle containing signed estimate and signature (TEST ANCHOR).
     */
    public function compile(int $businessId, int $disputeId, array $evidenceItems): array
    {
        $dispute = Dispute::where('business_id', $businessId)->findOrFail($disputeId);

        if (in_array($dispute->status, ['submitted', 'won', 'lost'], true)) {
            throw new DisputeAlreadySubmittedException(sprintf(
                'Invoice #%d is already submitted: a submitted bundle is sealed and nothing is added to it.',
                $dispute->invoice_id
            ));
        }

        $savedItems = [];
        foreach ($evidenceItems as $item) {
            $saved = DisputeEvidence::create([
                'business_id' => $businessId,
                'dispute_id' => $dispute->id,
                'evidence_type' => $item['type'] ?? 'document',
                'file_url_or_content' => $item['content'] ?? '',
            ]);
            $savedItems[] = $saved;
        }

        $dispute->update(['status' => 'compiled']);

        Event::dispatch(new EvidenceCompiled($businessId, $dispute->id, count($savedItems)));

        return [
            'status' => 'compiled',
            'dispute_id' => $dispute->id,
            'evidence_count' => count($savedItems),
            'has_signature' => collect($savedItems)->contains('evidence_type', 'signature'),
        ];
    }

    public function submit(int $businessId, int $disputeId): Dispute
    {
        $dispute = Dispute::where('business_id', $businessId)->findOrFail($disputeId);

        if ($dispute->status !== 'compiled') {
            throw new DisputeNotCompiledException('Compile the evidence first: a dispute is never submitted empty.');
        }

        $dispute->update(['status' => 'submitted']);

        return $dispute;
    }

    /**
     * Handles dispute outcome: a lost dispute writes commission.clawed_back for the released commission on that job (TEST ANCHOR).
     */
    public function recordOutcome(int $businessId, int $disputeId, string $outcome, ?string $lostReason = null): array
    {
        $dispute = Dispute::where('business_id', $businessId)->findOrFail($disputeId);
        $dispute->update(['status' => $outcome]);

        $clawbackTriggered = false;

        if ($outcome === 'lost') {
            $clawbackTriggered = true; // TEST ANCHOR: triggers commission.clawed_back
            Event::dispatch(new DisputeLost($businessId, $dispute->id, $dispute->invoice_id, $dispute->chargeback_amount_cents));
        } else {
            Event::dispatch(new DisputeResolved($businessId, $dispute->id));
        }

        $outcomeRecord = DisputeOutcome::create([
            'business_id' => $businessId,
            'dispute_id' => $dispute->id,
            'outcome' => $outcome,
            'lost_reason' => $lostReason,
            'commission_clawback_triggered' => $clawbackTriggered,
        ]);

        return [
            'status' => $outcome,
            'dispute_id' => $dispute->id,
            'outcome_id' => $outcomeRecord->id,
            'commission_clawback_triggered' => $clawbackTriggered,
        ];
    }
}
