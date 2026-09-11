<?php

declare(strict_types=1);

namespace App\Modules\X211\Actions;

use App\Modules\X211\Domain\ArEngine;
use App\Modules\X211\Models\ArDunningAction;

final class ArRecordReasonAction
{
    public function __construct(private readonly ArEngine $engine) {}

    public function handle(int $businessId, int $invoiceId, string $reasonCode): ArDunningAction
    {
        return $this->engine->recordReason($businessId, $invoiceId, $reasonCode);
    }
}
