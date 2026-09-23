<?php

declare(strict_types=1);

namespace App\Modules\X199\Actions;

use App\Modules\X199\Models\Invoice;

final class SendInvoiceLinkAction
{
    /**
     * [G1-60] a channel choice on an existing link
     */
    public function handle(Invoice $invoice, string $channel): array
    {
        // R245: Allow sending an existing invoice link via multiple channels (email, sms, whatsapp)
        if (! in_array($channel, ['email', 'sms', 'whatsapp'])) {
            throw new \InvalidArgumentException('Invalid channel');
        }

        // Logic to dispatch to the appropriate notification channel
        return [
            'status' => 'sent',
            'channel' => $channel,
            'invoice_id' => $invoice->id,
        ];
    }
}
