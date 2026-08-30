<?php

declare(strict_types=1);

namespace App\Modules\X199\Actions;

use App\Modules\X199\Domain\InvoiceEngine;

final class InvoiceRecordOfflineAction
{
    public function __construct(private readonly InvoiceEngine $engine) {}

    public function handle(int $businessId, int $invoiceId, int $amountCents, string $offlineMethod = 'check'): array
    {
        return $this->engine->recordPayment($businessId, $invoiceId, $amountCents);
    }
}
