<?php

declare(strict_types=1);

namespace App\Modules\X112\Actions;

use App\Modules\X112\Domain\AgencyEngine;
use App\Modules\X112\Models\AgencyClient;

final class AgencyOnboardClientAction
{
    public function __construct(private readonly AgencyEngine $engine) {}

    public function handle(int $businessId, int $agencyId, string $clientName): AgencyClient
    {
        return $this->engine->onboardClient($businessId, $agencyId, $clientName);
    }
}
