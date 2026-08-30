<?php

declare(strict_types=1);

namespace App\Modules\X218\Actions;

use App\Modules\X218\Events\SendRequested;
use App\Modules\X218\Models\InfluencerProfile;
use Illuminate\Support\Facades\Event;

final class InfluencerOutreachAction
{
    public function proposeCollab(int $businessId, int $influencerId): void
    {
        $influencer = InfluencerProfile::where('business_id', $businessId)->findOrFail($influencerId);

        Event::dispatch(new SendRequested($businessId, $influencer->handle, 'influencer_dm'));
    }
}
