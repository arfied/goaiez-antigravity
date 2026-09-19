<?php

declare(strict_types=1);

namespace App\Modules\X199\Listeners;

use App\Modules\X199\Events\InvoiceOpened;
use Illuminate\Support\Facades\Log;

final class NotifyInvoiceOpened
{
    /**
     * [G13-36] invoice opened the alert names an action
     */
    public function handle(InvoiceOpened $event): void
    {
        // R245: Fire an alert that suggests a follow-up action when the invoice is viewed
        Log::info('Alert: Invoice opened.', [
            'business_id' => $event->businessId,
            'invoice_id' => $event->invoiceId,
            'suggested_action' => 'record_payment', // The alert names an action
        ]);
    }
}
