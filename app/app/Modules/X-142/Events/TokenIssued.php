<?php

declare(strict_types=1);

namespace App\Modules\X142\Events;

use Illuminate\Foundation\Events\Dispatchable;

class TokenIssued
{
    use Dispatchable;

    public function __construct(public int $businessId, public int $tokenId) {}
}
