<?php

declare(strict_types=1);

namespace App\Modules\X211\Actions;

use App\Modules\X211\Domain\ArEngine;
use App\Modules\X211\Models\ArPlanTerm;

final class ArSetLateFeeTermAction
{
    public function __construct(
        private readonly ArEngine $engine
    ) {}

    public function handle(int $businessId, int $percent, ?int $capCents): ArPlanTerm
    {
        return $this->engine->setLateFeeTerm($businessId, $percent, $capCents);
    }
}
