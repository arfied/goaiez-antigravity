<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Actions;

use App\Modules\CBilling\Domain\BillingLedgerEngine;
use App\Modules\CBilling\Models\DunningState;

final class DunningAdvanceAction
{
    public function __construct(private readonly BillingLedgerEngine $engine) {}

    public function handle(int $businessId, int $dayInCycle): DunningState
    {
        return $this->engine->advanceDunning($businessId, $dayInCycle);
    }
}
