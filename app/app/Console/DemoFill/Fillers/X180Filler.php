<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X180\Models\ContentPack;

class X180Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-180';
    }

    public function fill(Business $business): int
    {
        if (ContentPack::where('business_id', $business->id)->where('pack_name', 'like', self::MARKER.'180%')->exists()) {
            return 0;
        }

        ContentPack::create(['business_id' => $business->id, 'pack_name' => self::MARKER.'180 Pack', 'assets_count' => 5, 'assets_manifest' => []]);

        return 1;
    }

    public function purge(Business $business): int
    {
        return ContentPack::where('business_id', $business->id)->where('pack_name', 'like', self::MARKER.'180%')->delete();
    }
}
