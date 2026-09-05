<?php

declare(strict_types=1);

namespace App\Modules\X205\Actions;

use App\Modules\X205\Events\ApprovalRequested;
use App\Modules\X205\Models\Affiliate;
use App\Modules\X205\Models\AffiliatePayout;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class AffiliatePayoutRequestAction
{
    public function requestPayout(int $businessId, int $affiliateId, int $amountCents): AffiliatePayout
    {
        $affiliate = Affiliate::where('business_id', $businessId)->findOrFail($affiliateId);

        if ($amountCents > $affiliate->current_balance_cents) {
            throw new InvalidArgumentException('Payout rejected: amount exceeds available current balance');
        }

        // G10-36: W9 threshold as data on affiliate
        $status = 'requested';
        if ($affiliate->w9_threshold_cents !== null && ! $affiliate->w9_on_file && $affiliate->lifetime_earnings_cents >= $affiliate->w9_threshold_cents) {
            $status = 'frozen';
        }

        $payout = AffiliatePayout::create([
            'business_id' => $businessId,
            'affiliate_id' => $affiliate->id,
            'amount_cents' => $amountCents,
            'status' => $status,
            'money_moved' => false, // Moves no money until approved
        ]);

        Event::dispatch(new ApprovalRequested(
            businessId: $businessId,
            requestType: 'payout_request',
            referenceId: $payout->id,
            amountCents: $amountCents,
            status: $status
        ));

        return $payout;
    }
}
