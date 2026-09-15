<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X175\Models\FieldSuggestion;

class X175Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-175';
    }

    public function fill(Business $business): int
    {
        if (FieldSuggestion::where('business_id', $business->id)->where('query_text', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }
        for ($i = 0; $i < 3; $i++) {
            FieldSuggestion::create(['business_id' => $business->id, 'query_text' => self::MARKER."Sugg $i", 'response_text' => 'Demo response']);
        }

        return 3;
    }

    public function purge(Business $business): int
    {
        return FieldSuggestion::where('business_id', $business->id)->where('query_text', 'like', self::MARKER.'%')->delete();
    }
}
