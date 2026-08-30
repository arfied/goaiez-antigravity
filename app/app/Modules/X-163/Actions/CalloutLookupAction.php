<?php

declare(strict_types=1);

namespace App\Modules\X163\Actions;

use App\Modules\X163\Domain\PricebookEngine;

final class CalloutLookupAction
{
    public function __construct(private readonly PricebookEngine $engine) {}

    public function handle(int $businessId, ?int $locationBookId = null): array
    {
        return $this->engine->lookupCallout($businessId, $locationBookId);
    }
}
