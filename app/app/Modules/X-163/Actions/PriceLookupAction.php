<?php

declare(strict_types=1);

namespace App\Modules\X163\Actions;

use App\Modules\X163\Domain\PricebookEngine;

final class PriceLookupAction
{
    public function __construct(private readonly PricebookEngine $engine) {}

    public function handle(int $businessId, string $serviceName, string $channel = 'customer', ?int $locationBookId = null): array
    {
        return $this->engine->lookup($businessId, $serviceName, $channel, $locationBookId);
    }
}
