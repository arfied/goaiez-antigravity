<?php

declare(strict_types=1);

namespace App\Modules\X204\Listeners;

use App\Modules\X121\Actions\PersonLookupAction;
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
        $personId = app(PersonLookupAction::class)->idForPhone($event->businessId, $event->recipientPhone);

        if ($personId !== null) {
            $this->sequenceStopAction->stopAllSequencesForPerson(
                $event->businessId,
                $personId,
                'sms'
            );
        }
    }
}
