<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X185\Models\Sequence;
use App\Modules\X185\Models\ContentPack;

class X185Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-185';
    }

    public function fill(Business $business): int
    {
        if (Sequence::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        Sequence::create(['business_id' => $business->id, 'name' => self::MARKER.'Seq', 'is_active' => true]);
        ContentPack::create(['business_id' => $business->id, 'pack_name' => self::MARKER.'185 Pack', 'fleet_sample_size' => 100, 'is_promoted' => true]);

        return 2;
    }

    public function purge(Business $business): int
    {
        $count = Sequence::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->delete();
        $count += ContentPack::where('business_id', $business->id)->where('pack_name', 'like', self::MARKER.'185%')->delete();
        return $count;
    }
}
