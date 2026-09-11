<?php

declare(strict_types=1);

namespace App\Modules\X198\Actions;

use App\Modules\X198\Domain\GatewayEngine;
use App\Modules\X198\Models\ReconciliationRun;

class DiscrepancyReviewAction
{
    public function __construct(
        private readonly GatewayEngine $engine
    ) {}

    public function handle(int $businessId, int $runId, int $userId): ReconciliationRun
    {
        return $this->engine->reviewDiscrepancy($businessId, $runId, $userId);
    }
}
