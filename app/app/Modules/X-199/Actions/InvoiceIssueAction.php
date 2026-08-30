<?php

declare(strict_types=1);

namespace App\Modules\X199\Actions;

use App\Modules\X199\Domain\InvoiceEngine;

final class InvoiceIssueAction
{
    public function __construct(private readonly InvoiceEngine $engine) {}

    public function handle(int $businessId, int $customerId, array $lines, string $termsType = 'net_30'): array
    {
        return $this->engine->issueInvoice($businessId, $customerId, $lines, $termsType);
    }
}
