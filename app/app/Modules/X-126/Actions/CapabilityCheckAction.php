<?php

declare(strict_types=1);

namespace App\Modules\X126\Actions;

use App\Modules\X126\Domain\CapabilityArbiter;

final class CapabilityCheckAction
{
    public function __construct(private readonly CapabilityArbiter $arbiter) {}

    public function handle(int $businessId, string $capabilityName, array $context = []): array
    {
        return $this->arbiter->check($businessId, $capabilityName, $context);
    }
}
