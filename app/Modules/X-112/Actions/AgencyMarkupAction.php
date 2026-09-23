<?php

declare(strict_types=1);

namespace App\Modules\X112\Actions;

use App\Modules\X112\Domain\AgencyEngine;
use App\Modules\X112\Models\Markup;

final class AgencyMarkupAction
{
    public function __construct(private readonly AgencyEngine $engine) {}

    public function handle(int $businessId, int $agencyId, string $serviceType, int $wholesaleRateCents, int $retailMarkupCents): Markup
    {
        return $this->engine->setMarkup($businessId, $agencyId, $serviceType, $wholesaleRateCents, $retailMarkupCents);
    }
}
