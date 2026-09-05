<?php

declare(strict_types=1);

namespace App\Modules\X205\Actions;

use App\Modules\X205\Events\ApprovalRequested;
use App\Modules\X205\Models\AffiliateAttribution;
use Illuminate\Support\Facades\Event;

final class AffiliateProposeClawbackAction
{
    /**
     * Proposes a commission clawback on refunded order.
     * TEST ANCHOR: A refund on an attributed sale produces a clawback PROPOSAL and moves NO money.
     */
    public function proposeClawback(int $businessId, int $attributionId, string $refundReason = 'Customer order refunded', ?string $disputeRef = null): AffiliateAttribution
    {
        $attribution = AffiliateAttribution::where('business_id', $businessId)->findOrFail($attributionId);

        // TEST ANCHOR: Status set to proposed, is_clawed_back remains false until human approval (moves no money)
        // G7-11: triggering refund/dispute attached
        $attribution->update([
            'clawback_status' => 'proposed',
            'is_clawed_back' => false, // No money moved
            'triggering_dispute_ref' => $disputeRef,
        ]);

        Event::dispatch(new ApprovalRequested(
            businessId: $businessId,
            requestType: 'clawback_proposal',
            referenceId: $attribution->id,
            amountCents: $attribution->commission_cents
        ));

        return $attribution;
    }
}
