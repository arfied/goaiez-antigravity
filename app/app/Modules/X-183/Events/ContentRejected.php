<?php

declare(strict_types=1);

namespace App\Modules\X183\Events;

final class ContentRejected
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $draftId,
        public readonly string $reason
    ) {}
}
