<?php

declare(strict_types=1);

namespace App\Modules\X218\Actions;

use App\Modules\X218\Events\InfluencerEngaged;
use App\Modules\X218\Models\InfluencerDeal;
use App\Modules\X218\Models\InfluencerProfile;
use Illuminate\Support\Facades\Event;

final class InfluencerDealAction
{
    public function createDeal(int $businessId, int $influencerId, int $dealAmountCents): InfluencerDeal
    {
        $influencer = InfluencerProfile::where('business_id', $businessId)->findOrFail($influencerId);

        $deal = InfluencerDeal::create([
            'business_id' => $businessId,
            'influencer_id' => $influencer->id,
            'deal_amount_cents' => $dealAmountCents,
            'status' => 'active',
            'is_paid' => false,
        ]);

        Event::dispatch(new InfluencerEngaged($businessId, $deal->id, $influencer->id));

        return $deal;
    }
}
