<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Models\Location;
use App\Modules\X177\Models\GbpConnection;
use App\Modules\X177\Models\GbpPost;
use App\Modules\X177\Models\GbpStateLog;

class X177Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-177';
    }

    public function fill(Business $business): int
    {
        if (GbpConnection::where('business_id', $business->id)->where('external_label', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $loc = Location::firstOrCreate(
            ['business_id' => $business->id, 'name' => self::MARKER.'Demo Location'],
            ['google_location_id' => self::MARKER.'locations/demo']
        );

        $conn = GbpConnection::create([
            'business_id' => $business->id,
            'account_ref' => 'acct_demo',
            'external_label' => self::MARKER.'Demo Store',
            'location_id' => $loc->id,
            'profile_status' => 'active',
        ]);

        GbpPost::create([
            'business_id' => $business->id,
            'connection_id' => $conn->id,
            'content' => self::MARKER.'Demo post 1',
            'status' => 'published',
        ]);

        GbpPost::create([
            'business_id' => $business->id,
            'connection_id' => $conn->id,
            'content' => self::MARKER.'Demo risky post',
            'status' => 'rejected_risk',
        ]);

        GbpStateLog::create([
            'business_id' => $business->id,
            'connection_id' => $conn->id,
            'event_type' => self::MARKER.'demo_event',
            'details' => [],
        ]);

        return 4;
    }

    public function purge(Business $business): int
    {
        $logCount = GbpStateLog::where('business_id', $business->id)
            ->where('event_type', 'like', self::MARKER.'%')
            ->delete();

        $postCount = GbpPost::where('business_id', $business->id)
            ->where('content', 'like', self::MARKER.'%')
            ->delete();

        $connCount = GbpConnection::where('business_id', $business->id)
            ->where('external_label', 'like', self::MARKER.'%')
            ->delete();

        $locCount = Location::where('business_id', $business->id)
            ->where('name', 'like', self::MARKER.'%')
            ->delete();

        return $logCount + $postCount + $connCount + $locCount;
    }
}
