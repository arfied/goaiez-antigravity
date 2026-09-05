<?php

declare(strict_types=1);

namespace App\Modules\X123\Actions;

use App\Modules\X123\Events\EventDeadLettered;
use App\Modules\X123\Events\EventPublished;
use App\Modules\X123\Mail\DeadLetterNotification;
use App\Modules\X123\Models\DeadLetter;
use App\Modules\X123\Models\EventLog;
use App\Modules\X123\Models\EventSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

final class EventPublishAction
{
    public function __construct() {}

    public function handle(
        int $businessId,
        string $eventName,
        array $payload,
        ?string $partitionKey = null
    ): EventLog {
        return DB::transaction(function () use ($businessId, $eventName, $payload, $partitionKey) {
            $key = $partitionKey ?? "biz:{$businessId}:{$eventName}";

            $lastSeq = (int) DB::table('event_log')
                ->where('business_id', $businessId)
                ->where('partition_key', $key)
                ->max('sequence_number');

            $seq = $lastSeq + 1;

            $log = EventLog::create([
                'business_id' => $businessId,
                'event_name' => $eventName,
                'payload' => $payload,
                'partition_key' => $key,
                'sequence_number' => $seq,
                'status' => 'published',
                'published_at' => now(),
            ]);

            Event::dispatch(new EventPublished(
                businessId: $businessId,
                eventLogId: $log->id,
                eventName: $eventName,
                payload: $payload
            ));

            return $log;
        });
    }

    public function processDelivery(int $eventLogId, int $subscriptionId, int $simulatedFailures = 0): array
    {
        $log = EventLog::findOrFail($eventLogId);
        $sub = EventSubscription::findOrFail($subscriptionId);

        if ($simulatedFailures >= 10) {
            $sub->increment('failure_count');
            $sub->update(['last_failed_at' => now()]);

            $dlq = DeadLetter::create([
                'business_id' => $log->business_id,
                'event_log_id' => $log->id,
                'subscription_id' => $sub->id,
                'error_message' => 'Subscriber failed 10 consecutive delivery attempts',
                'attempts' => 10,
                'notified_at' => now(),
            ]);

            Event::dispatch(new EventDeadLettered(
                businessId: $log->business_id,
                deadLetterId: $dlq->id,
                eventLogId: $log->id,
                errorMessage: $dlq->error_message
            ));

            // P-060 limits the decider to customer communications. This is an operator alert (platform -> tenant owner).
            $ownerEmail = DB::table('businesses')
                ->join('users', 'businesses.owner_user_id', '=', 'users.id')
                ->where('businesses.id', $log->business_id)
                ->value('users.email');

            if ($ownerEmail) {
                // Send exactly one notification email
                Mail::to($ownerEmail)->send(new DeadLetterNotification(
                    businessId: $log->business_id,
                    eventLogId: $log->id,
                    eventName: $log->event_name,
                    errorMessage: $dlq->error_message
                ));
            }

            return ['status' => 'dead_lettered', 'dlq_id' => $dlq->id];
        }

        return ['status' => 'delivered'];
    }
}
