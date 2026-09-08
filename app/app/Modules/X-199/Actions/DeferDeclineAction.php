<?php

declare(strict_types=1);

namespace App\Modules\X199\Actions;

use App\Modules\X198\Models\Payment;
use App\Modules\X199\Models\DeclineDeferral;

final class DeferDeclineAction
{
    public function handle(int $businessId, int $paymentId): DeclineDeferral
    {
        Payment::where('business_id', $businessId)->findOrFail($paymentId);

        return DeclineDeferral::firstOrCreate([
            'business_id' => $businessId,
            'payment_id' => $paymentId,
        ]);
    }
}
