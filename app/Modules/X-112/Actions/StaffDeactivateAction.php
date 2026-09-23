<?php

declare(strict_types=1);

namespace App\Modules\X112\Actions;

use App\Modules\X112\Domain\AgencyEngine;

final class StaffDeactivateAction
{
    public function __construct(private readonly AgencyEngine $engine) {}

    public function handle(int $businessId, int $agencyId, int $userId): void
    {
        $this->engine->deactivateStaff($businessId, $agencyId, $userId);
    }
}
