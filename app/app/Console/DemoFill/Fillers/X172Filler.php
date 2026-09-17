<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X172\Models\PortalLink;
use App\Modules\X172\Models\PortalView;

class X172Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-172';
    }

    public function fill(Business $business): int
    {
        if (PortalLink::where('business_id', $business->id)->where('is_sample', true)->exists()) {
            return 0;
        }

        $link = PortalLink::create([
            'business_id' => $business->id,
            'customer_id' => null,
            'resource_type' => 'job',
            'resource_id' => 100,
            'token' => 'demo-portal-'.$business->id,
            'expires_at' => now()->addDays(30),
            'is_active' => true,
            'is_sample' => true,
        ]);

        PortalView::create([
            'business_id' => $business->id,
            'portal_link_id' => $link->id,
            'customer_id' => null,
            'ip_address' => '203.0.113.10',
            'user_agent' => self::MARKER.'Safari on iPhone',
            'opened_at' => now()->subHour(),
            'action_taken' => null,
        ]);

        return 2;
    }

    public function purge(Business $business): int
    {
        $linkIds = PortalLink::where('business_id', $business->id)
            ->where('is_sample', true)
            ->pluck('id');

        $count = PortalView::where('business_id', $business->id)
            ->whereIn('portal_link_id', $linkIds)
            ->delete();

        $count += PortalLink::where('business_id', $business->id)
            ->whereIn('id', $linkIds)
            ->delete();

        return $count;
    }
}
