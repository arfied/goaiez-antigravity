<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Listeners;

use App\Modules\CBilling\Domain\BillingLedgerEngine;
use App\Modules\X198\Events\PaymentCaptured;

class CreditAccountOnPaymentCaptured
{
    public function handle(PaymentCaptured $event): void
    {
        if ($event->amountCents <= 0) {
            return;
        }

        $engine = new BillingLedgerEngine();
        $engine->topup($event->businessId, $event->amountCents);
    }
}
