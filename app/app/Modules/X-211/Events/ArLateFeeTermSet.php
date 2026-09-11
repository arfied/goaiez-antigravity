<?php

declare(strict_types=1);

namespace App\Modules\X211\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ArLateFeeTermSet
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $businessId,
        public readonly int $percent,
        public readonly ?int $capCents
    ) {}
}
