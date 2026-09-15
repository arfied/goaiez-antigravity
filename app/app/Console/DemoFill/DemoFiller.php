<?php

namespace App\Console\DemoFill;

use App\Models\Business;

interface DemoFiller
{
    public const MARKER = 'demo·';

    public function module(): string;

    public function fill(Business $business): int;

    public function purge(Business $business): int;
}
