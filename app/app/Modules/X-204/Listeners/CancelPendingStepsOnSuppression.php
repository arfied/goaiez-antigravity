<?php

declare(strict_types=1);

namespace App\Modules\X204\Listeners;

use App\Models\Customer;
use App\Modules\X186\Actions\SequenceStopAction;
use App\Modules\X204\Events\SuppressionAdded;

final class CancelPendingStepsOnSuppression
{
    public function __construct(
        private readonly SequenceStopAction $sequenceStopAction
    ) {
    }

    public function handle(SuppressionAdded $event): void
    {
        $customer = Customer::where('business_id', $event->businessId)
            ->where('phone', $event->recipientPhone)
            ->first();

        if ($customer !== null) {
            $this->sequenceStopAction->stopAllSequencesForPerson(
                $event->businessId,
                $customer->id,
                'sms'
            );
        }
    }
}
