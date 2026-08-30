<?php

declare(strict_types=1);

namespace App\Modules\X123\Actions;

use App\Modules\X123\Models\DeadLetter;
use App\Modules\X123\Models\EventLog;

final class EventReplayAction
{
    public function __construct(private readonly EventPublishAction $publisher) {}

    public function handle(int $deadLetterId, int $businessId): array
    {
        $dlq = DeadLetter::where('business_id', $businessId)->findOrFail($deadLetterId);
        $log = EventLog::where('business_id', $businessId)->findOrFail($dlq->event_log_id);

        $replayed = $this->publisher->handle(
            businessId: $businessId,
            eventName: $log->event_name,
            payload: $log->payload,
            partitionKey: $log->partition_key
        );

        $dlq->update(['replayed_at' => now()]);

        return [
            'replayed_event_id' => $replayed->id,
            'dead_letter_id' => $dlq->id,
            'status' => 'replayed',
        ];
    }
}
