<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X08\Models\ChurnScore;

class X08Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-08';
    }

    public function fill(Business $business): int
    {
        if (ChurnScore::where('business_id', $business->id)->where('recommendation_note', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        ChurnScore::create(['business_id' => $business->id, 'tenant_identifier' => 'tenant-1', 'risk_level' => 'demo-low', 'recommendation_note' => self::MARKER.'Just chilling']);
        ChurnScore::create(['business_id' => $business->id, 'tenant_identifier' => 'tenant-2', 'risk_level' => 'demo-med', 'recommendation_note' => self::MARKER.'Hmm']);
        ChurnScore::create(['business_id' => $business->id, 'tenant_identifier' => 'tenant-3', 'risk_level' => 'demo-high', 'recommendation_note' => self::MARKER.'Panic']);

        return 3;
    }

    public function purge(Business $business): int
    {
        return ChurnScore::where('business_id', $business->id)->where('recommendation_note', 'like', self::MARKER.'%')->delete();
    }
}
