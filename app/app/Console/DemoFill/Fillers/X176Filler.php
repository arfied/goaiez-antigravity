<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X176\Models\SchemaSnapshot;

class X176Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-176';
    }

    public function fill(Business $business): int
    {
        if (SchemaSnapshot::where('business_id', $business->id)->where('entity_type', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        SchemaSnapshot::create([
            'business_id' => $business->id,
            'page_id' => 1,
            'entity_type' => self::MARKER.'LocalBusiness',
            'json_ld' => '{}',
            'commit_id' => 'commit1',
            'is_valid_schema' => true,
        ]);

        return 1;
    }

    public function purge(Business $business): int
    {
        return SchemaSnapshot::where('business_id', $business->id)
            ->where('entity_type', 'like', self::MARKER.'%')
            ->delete();
    }
}
