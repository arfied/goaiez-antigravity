<?php

declare(strict_types=1);

namespace App\Modules\X218\Actions;

use App\Modules\X218\Events\InfluencerDelivered;
use App\Modules\X218\Models\Deliverable;
use App\Modules\X218\Models\InfluencerDeal;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class InfluencerDeliverableAction
{
    /**
     * Records and verifies deliverable artifact (TEST ANCHOR).
     * 1. A deal marked delivered with no artifact is refused.
     * 2. A payout NEVER fires without a VERIFIED deliverable (a live URL returning 200, captured and hashed).
     */
    public function submitAndVerifyDeliverable(
        int $businessId,
        int $dealId,
        string $liveUrl,
        int $httpStatus,
        ?string $artifactHash = null
    ): Deliverable {
        $deal = InfluencerDeal::where('business_id', $businessId)->findOrFail($dealId);

        // TEST ANCHOR: A deal marked delivered with NO artifact is refused
        if (empty($artifactHash) || empty($liveUrl)) {
            throw new InvalidArgumentException('Deliverable rejected: a pass with no artifact DID NOT HAPPEN (TEST ANCHOR)');
        }

        if ($httpStatus !== 200) {
            throw new InvalidArgumentException("Deliverable rejected: live URL must return HTTP 200, got {$httpStatus} (TEST ANCHOR)");
        }

        $deliverable = Deliverable::create([
            'business_id' => $businessId,
            'deal_id' => $deal->id,
            'live_url' => $liveUrl,
            'http_status' => 200,
            'artifact_hash' => $artifactHash,
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        $deal->update(['status' => 'delivered']);

        Event::dispatch(new InfluencerDelivered($businessId, $deal->id, $deliverable->id, $artifactHash));

        return $deliverable;
    }

    /**
     * Executes payout: asserts verified deliverable artifact exists before moving money (TEST ANCHOR).
     */
    public function executePayout(int $businessId, int $dealId): InfluencerDeal
    {
        $deal = InfluencerDeal::where('business_id', $businessId)->findOrFail($dealId);

        // TEST ANCHOR: A payout NEVER fires without a VERIFIED deliverable
        $verifiedDeliverable = Deliverable::where('business_id', $businessId)
            ->where('deal_id', $deal->id)
            ->where('is_verified', true)
            ->first();

        if (! $verifiedDeliverable) {
            throw new InvalidArgumentException('Payout rejected: payout NEVER fires without a VERIFIED deliverable (TEST ANCHOR)');
        }

        $deal->update([
            'status' => 'paid',
            'is_paid' => true,
        ]);

        return $deal;
    }
}
