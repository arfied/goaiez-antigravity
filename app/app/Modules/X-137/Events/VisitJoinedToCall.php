<?php

declare(strict_types=1);

namespace App\Modules\X137\Events;

final class VisitJoinedToCall
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $callId,
        public readonly string $visitorSessionToken
    ) {}
}
