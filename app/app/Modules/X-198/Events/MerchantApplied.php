<?php

declare(strict_types=1);

namespace App\Modules\X198\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MerchantApplied
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $businessId,
        public int $connectionId,
        public string $applicationRef
    ) {}
}
