<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X129\Models\RedirectMap;

class X129Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-129';
    }

    public function fill(Business $business): int
    {
        if (RedirectMap::where('business_id', $business->id)->where('source_url', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        RedirectMap::create([
            'business_id' => $business->id,
            'source_url' => self::MARKER.'/old-home',
            'destination_url' => self::MARKER.'/new-home',
            'status_code' => 301,
            'is_verified' => true,
        ]);
        RedirectMap::create([
            'business_id' => $business->id,
            'source_url' => self::MARKER.'/old-contact',
            'destination_url' => self::MARKER.'/new-contact',
            'status_code' => 301,
            'is_verified' => true,
        ]);
        RedirectMap::create([
            'business_id' => $business->id,
            'source_url' => self::MARKER.'/old-about',
            'destination_url' => self::MARKER.'/new-about',
            'status_code' => 302,
            'is_verified' => false,
        ]);

        return 3;
    }

    public function purge(Business $business): int
    {
        return RedirectMap::where('business_id', $business->id)->where('source_url', 'like', self::MARKER.'%')->delete();
    }
}
