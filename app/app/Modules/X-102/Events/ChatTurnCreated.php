<?php

declare(strict_types=1);

namespace App\Modules\X102\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class ChatTurnCreated
{
    use Dispatchable;

    public function __construct(
        public readonly int $businessId,
        public readonly int $turnId
    ) {}
}
