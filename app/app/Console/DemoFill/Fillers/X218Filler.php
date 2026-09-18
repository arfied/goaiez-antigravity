<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X218\Models\Deliverable;
use App\Modules\X218\Models\InfluencerDeal;
use App\Modules\X218\Models\InfluencerProfile;

class X218Filler implements DemoFiller
{
    public const MARKER = 'demo·';

    public function module(): string
    {
        return 'X-218';
    }

    public function fill(Business $business): int
    {
        if (InfluencerProfile::where('business_id', $business->id)->where('handle', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $influencer1 = InfluencerProfile::create([
            'business_id' => $business->id,
            'handle' => self::MARKER.'techguru',
            'platform' => 'youtube',
            'audience_size' => 150000,
            'engagement_rate' => 4.5,
        ]);

        $deal1 = InfluencerDeal::create([
            'business_id' => $business->id,
            'influencer_id' => $influencer1->id,
            'deal_amount_cents' => 50000,
            'status' => 'active',
            'is_paid' => false,
        ]);

        Deliverable::create([
            'business_id' => $business->id,
            'deal_id' => $deal1->id,
            'live_url' => 'https://youtube.com/watch?v=demo1',
            'http_status' => 200,
            'artifact_hash' => 'hash123',
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        $influencer2 = InfluencerProfile::create([
            'business_id' => $business->id,
            'handle' => self::MARKER.'home_hacks',
            'platform' => 'instagram',
            'audience_size' => 80000,
            'engagement_rate' => 6.2,
        ]);

        InfluencerDeal::create([
            'business_id' => $business->id,
            'influencer_id' => $influencer2->id,
            'deal_amount_cents' => 25000,
            'status' => 'proposed',
            'is_paid' => false,
        ]);

        return 5;
    }

    public function purge(Business $business): int
    {
        $profiles = InfluencerProfile::where('business_id', $business->id)->where('handle', 'like', self::MARKER.'%')->get();

        $count = 0;
        foreach ($profiles as $profile) {
            $deals = InfluencerDeal::where('influencer_id', $profile->id)->get();
            foreach ($deals as $deal) {
                $count += Deliverable::where('deal_id', $deal->id)->delete();
                $count += $deal->delete();
            }
            $count += $profile->delete();
        }

        return $count;
    }
}
