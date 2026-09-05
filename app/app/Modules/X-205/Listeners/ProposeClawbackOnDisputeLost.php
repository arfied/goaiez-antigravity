<?php

declare(strict_types=1);

namespace App\Modules\X205\Listeners;

use App\Modules\X201\Events\DisputeLost;
use App\Modules\X205\Actions\AffiliateProposeClawbackAction;
use App\Modules\X205\Models\AffiliateAttribution;

final class ProposeClawbackOnDisputeLost
{
    private AffiliateProposeClawbackAction $clawbackAction;

    public function __construct(AffiliateProposeClawbackAction $clawbackAction)
    {
        $this->clawbackAction = $clawbackAction;
    }

    public function handle(DisputeLost $event): void
    {
        // G7-11: Clawback Automation.
        // A chargeback (DisputeLost) produces a clawback PROPOSAL with the triggering refund attached, moves no money.
        $attribution = AffiliateAttribution::where('business_id', $event->businessId)
            ->where('order_id', (string) $event->invoiceId)
            ->first();

        if ($attribution) {
            $this->clawbackAction->proposeClawback(
                $event->businessId,
                $attribution->id,
                'Chargeback dispute lost',
                (string) $event->disputeId
            );
        }
    }
}
