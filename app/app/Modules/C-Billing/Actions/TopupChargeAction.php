<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Actions;

use App\Modules\CBilling\Domain\BillingLedgerEngine;

final class TopupChargeAction
{
    public function __construct(private readonly BillingLedgerEngine $engine) {}

    public function handle(int $businessId, int $amountCents): array
    {
        return $this->engine->topup($businessId, $amountCents);
    }
}
