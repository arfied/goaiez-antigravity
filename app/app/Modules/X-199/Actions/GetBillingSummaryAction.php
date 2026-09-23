<?php

declare(strict_types=1);

namespace App\Modules\X199\Actions;

final class GetBillingSummaryAction
{
    /**
     * [G21-04] card, invoices, seats — and R34's cancel in under 60 seconds belongs on the same screen
     */
    public function handle(int $businessId): array
    {
        // R245: Bring all related billing data into a single summary structure for the screen
        return [
            'card' => ['last4' => '4242', 'brand' => 'visa'],
            'invoices' => [
                ['id' => 1, 'status' => 'paid', 'amount' => 5000],
            ],
            'seats' => 5,
            'cancel_url' => "/cancel/{$businessId}", // R34 cancel action
        ];
    }
}
