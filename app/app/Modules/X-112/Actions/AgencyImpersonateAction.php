<?php

declare(strict_types=1);

namespace App\Modules\X112\Actions;

use App\Modules\X112\Domain\AgencyEngine;
use App\Modules\X112\Models\ImpersonationLog;

final class AgencyImpersonateAction
{
    public function __construct(private readonly AgencyEngine $engine) {}

    public function handle(int $businessId, int $agencyId, int $userId, int $targetClientBusinessId, string $reason): ImpersonationLog
    {
        return $this->engine->impersonate($businessId, $agencyId, $userId, $targetClientBusinessId, $reason);
    }
}
