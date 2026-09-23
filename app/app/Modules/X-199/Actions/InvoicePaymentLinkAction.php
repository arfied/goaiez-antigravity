<?php

declare(strict_types=1);

namespace App\Modules\X199\Actions;

use App\Modules\X199\Models\Invoice;

final class InvoicePaymentLinkAction
{
    /**
     * [G1-51] gateway-agnostic — "Stripe" is corpus vocabulary
     */
    public function handle(Invoice $invoice, string $gateway = 'default'): string
    {
        // R245: The link URL does not hardcode gateway names in the routing layer.
        return "/pay/{$invoice->id}?gateway={$gateway}";
    }
}
