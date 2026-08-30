<?php

declare(strict_types=1);

namespace App\Modules\X172\Events;

final class PortalAction
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $portalLinkId,
        public readonly string $actionTaken,
        public readonly array $payload
    ) {}
}
