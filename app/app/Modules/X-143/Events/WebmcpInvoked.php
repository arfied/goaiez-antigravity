<?php

declare(strict_types=1);

namespace App\Modules\X143\Events;

final class WebmcpInvoked
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $contractName,
        public readonly int $jobId,
        public readonly string $actorType = 'webmcp'
    ) {}
}
