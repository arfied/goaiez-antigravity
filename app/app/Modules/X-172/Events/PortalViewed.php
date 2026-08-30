<?php

declare(strict_types=1);

namespace App\Modules\X172\Events;

final class PortalViewed
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $portalLinkId,
        public readonly string $resourceType,
        public readonly int $resourceId,
        public readonly string $openedAt
    ) {}
}
