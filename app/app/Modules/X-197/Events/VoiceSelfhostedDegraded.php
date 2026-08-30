<?php

declare(strict_types=1);

namespace App\Modules\X197\Events;

final class VoiceSelfhostedDegraded
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $reason
    ) {}
}
