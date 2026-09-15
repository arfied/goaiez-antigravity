<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X188\Models\NumberAssignment;
use App\Modules\X188\Models\NumberPark;
use App\Modules\X188\Models\NumberPool;

class X188Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-188';
    }

    public function fill(Business $business): int
    {
        // leave short: no boolean or free text column
        return 0;
    }

    public function purge(Business $business): int
    {
        return 0;
    }
}
