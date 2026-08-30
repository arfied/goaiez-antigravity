<?php

declare(strict_types=1);

namespace App\Modules\X105\Events;

final class OutreachSent
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $ladderId,
        public readonly int $rungNumber,
        public readonly string $channel
    ) {}
}
