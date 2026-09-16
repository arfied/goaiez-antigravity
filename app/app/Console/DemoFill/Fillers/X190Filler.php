<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X190\Models\PartnerPool;
use App\Modules\X190\Models\ReferralSlot;

class X190Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-190';
    }

    public function fill(Business $business): int
    {
        if (PartnerPool::where('business_id', $business->id)->where('company_name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        PartnerPool::create(['business_id' => $business->id, 'company_name' => self::MARKER.'Acme', 'category' => 'Retail', 'territory_zip' => '12345']);
        PartnerPool::create(['business_id' => $business->id, 'company_name' => self::MARKER.'Beta', 'category' => 'Service', 'territory_zip' => '67890']);

        ReferralSlot::create(['business_id' => $business->id, 'category' => self::MARKER.'Plumbing', 'territory_zip' => '11111', 'status' => 'open']);

        return 3;
    }

    public function purge(Business $business): int
    {
        $count = PartnerPool::where('business_id', $business->id)->where('company_name', 'like', self::MARKER.'%')->delete();
        $count += ReferralSlot::where('business_id', $business->id)->where('category', 'like', self::MARKER.'%')->delete();

        return $count;
    }
}
