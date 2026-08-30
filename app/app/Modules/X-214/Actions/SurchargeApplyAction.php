<?php

declare(strict_types=1);

namespace App\Modules\X214\Actions;

use App\Modules\X214\Domain\SurchargeEngine;

final class SurchargeApplyAction
{
    public function __construct(private readonly SurchargeEngine $engine = new SurchargeEngine) {}

    public function handle(int $businessId, string $transactionId): array
    {
        return $this->engine->apply($businessId, $transactionId);
    }
}
