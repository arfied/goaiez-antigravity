<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Actions;

use App\Modules\CBilling\Domain\BillingLedgerEngine;
use App\Modules\CBilling\Models\CreditLedgerEntry;

final class LedgerGrantAction
{
    public function __construct(private readonly BillingLedgerEngine $engine) {}

    public function handle(int $businessId, int $amountHundredthsCents, string $referenceId, string $description): CreditLedgerEntry
    {
        return $this->engine->grant($businessId, $amountHundredthsCents, $referenceId, $description);
    }
}
