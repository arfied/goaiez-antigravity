<?php

declare(strict_types=1);

namespace App\Modules\X157\Events;

final class DeployCompleted
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $deploymentId,
        public readonly string $domainName,
        public readonly string $deployHash
    ) {}
}
