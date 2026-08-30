<?php

declare(strict_types=1);

namespace App\Modules\X211\Actions;

use App\Modules\X211\Domain\ArEngine;

final class ArApplyLateFeeAction
{
    public function __construct(private readonly ArEngine $engine) {}

    public function handle(int $businessId, int $invoiceId, int $feeCents): array
    {
        return $this->engine->applyLateFee($businessId, $invoiceId, $feeCents);
    }
}
