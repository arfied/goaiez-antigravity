<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X165\Models\MembershipPlan;

class X165Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-165';
    }

    public function fill(Business $business): int
    {
        if (MembershipPlan::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }
        MembershipPlan::create(['business_id' => $business->id, 'name' => self::MARKER.'Plan A', 'price_cents' => 9900]);
        MembershipPlan::create(['business_id' => $business->id, 'name' => self::MARKER.'Plan B', 'price_cents' => 19900]);

        // Membership lacks a human-visible string column for the marker
        return 2;
    }

    public function purge(Business $business): int
    {
        return MembershipPlan::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->delete();
    }
}
