<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;

class X162Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-162';
    }

    public function fill(Business $business): int
    {
        // Route and DispatchAssignment lack human-visible string columns for the marker
        return 0;
    }

    public function purge(Business $business): int
    {
        return 0;
    }
}
