<?php

declare(strict_types=1);

namespace App\Modules\X211\Actions;

use App\Modules\X211\Domain\ArEngine;
use App\Modules\X211\Models\OfflinePayment;

final class ArLogOfflinePaymentAction
{
    public function __construct(private readonly ArEngine $engine) {}

    public function handle(int $businessId, int $invoiceId, int $amountCents, string $method = 'check', ?string $reference = null, ?string $photoPath = null): OfflinePayment
    {
        return $this->engine->logOfflinePayment($businessId, $invoiceId, $amountCents, $method, $reference, $photoPath);
    }
}
