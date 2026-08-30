<?php

declare(strict_types=1);

namespace App\Modules\X211\Actions;

final class ArForceAchAction
{
    public function handle(int $businessId, int $invoiceId, string $bankAccountToken): array
    {
        return [
            'invoice_id' => $invoiceId,
            'payment_method_forced' => 'ach_direct_debit',
            'bank_token' => $bankAccountToken,
            'status' => 'enforced',
        ];
    }
}
