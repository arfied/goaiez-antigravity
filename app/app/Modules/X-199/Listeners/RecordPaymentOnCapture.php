<?php

declare(strict_types=1);

namespace App\Modules\X199\Listeners;

use App\Modules\X198\Events\PaymentCaptured;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Modules\X199\Models\Invoice;

final class RecordPaymentOnCapture
{
    public function __construct(
        private readonly InvoiceEngine $engine
    ) {}

    public function handle(PaymentCaptured $event): void
    {
        $invoice = Invoice::where('business_id', $event->businessId)
            ->where('payment_id', $event->paymentId)
            ->first();

        if ($invoice !== null && $invoice->status !== 'paid') {
            $this->engine->recordPayment($event->businessId, $invoice->id, $event->amountCents);
        }
    }
}
