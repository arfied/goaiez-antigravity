<?php

declare(strict_types=1);

namespace App\Modules\X204\Listeners;

use App\Modules\X121\Models\Person;
use App\Modules\X186\Actions\SequenceStopAction;
use App\Modules\X204\Events\SuppressionAdded;

final class CancelPendingStepsOnSuppression
{
    public function __construct(
        private readonly SequenceStopAction $sequenceStopAction
    ) {}

    public function handle(SuppressionAdded $event): void
    {
        // Resolve the Person by business_id and phone; if no person exists, do nothing
        // (a suppression for a number with no person has no runs to stop).
        $person = Person::where('business_id', $event->businessId)
            ->where('phone', $event->recipientPhone)
            ->first();

        if ($person !== null) {
            $this->sequenceStopAction->stopAllSequencesForPerson(
                $event->businessId,
                $person->id,
                'sms'
            );
        }
    }
}
