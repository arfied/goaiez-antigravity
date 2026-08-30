<?php

declare(strict_types=1);

namespace App\Modules\X170\Actions;

use App\Modules\X170\Domain\CommissionEngine;

final class CommissionComputeAction
{
    public function __construct(private readonly CommissionEngine $engine = new CommissionEngine) {}

    public function handle(int $businessId, int $invoiceId, int $grossProfitCents, array $payeeSplits): array
    {
        return $this->engine->compute($businessId, $invoiceId, $grossProfitCents, $payeeSplits);
    }
}
