<?php

declare(strict_types=1);

namespace App\Modules\X201\Actions;

use App\Modules\X201\Domain\DisputeDefenseEngine;
use App\Modules\X201\Models\Dispute;

final class DisputeRecordAction
{
    public function __construct(private readonly DisputeDefenseEngine $engine = new DisputeDefenseEngine) {}

    public function handle(int $businessId, int $invoiceId, int $chargebackAmountCents, string $reason = 'fraudulent', string $gateway = ''): Dispute
    {
        return $this->engine->record($businessId, $invoiceId, $chargebackAmountCents, $reason, $gateway);
    }
}
