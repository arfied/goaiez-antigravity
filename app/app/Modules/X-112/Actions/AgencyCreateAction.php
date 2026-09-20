<?php

declare(strict_types=1);

namespace App\Modules\X112\Actions;

use App\Modules\X112\Domain\AgencyEngine;
use App\Modules\X112\Models\Agency;

final class AgencyCreateAction
{
    public function __construct(private readonly AgencyEngine $engine) {}

    public function handle(int $businessId, string $agencyName, ?string $whitelabelDomain = null, string $agencyMode = 'full_service'): Agency
    {
        return $this->engine->createAgency($businessId, $agencyName, $whitelabelDomain, $agencyMode);
    }
}
