<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X123\Models\DeadLetter;
use App\Modules\X123\Models\EventLog;

class X123Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-123';
    }

    public function fill(Business $business): int
    {
        if (DeadLetter::where('business_id', $business->id)->where('error_message', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $eventLog = EventLog::create([
            'business_id' => $business->id,
            'event_name' => self::MARKER.'review.reply.requested',
            'payload' => ['source' => 'demo'],
            'status' => 'published',
        ]);

        DeadLetter::create([
            'business_id' => $business->id,
            'event_log_id' => $eventLog->id,
            'subscription_id' => null,
            'error_message' => self::MARKER.'Webhook endpoint returned 503',
            'attempts' => 10,
            'notified_at' => now(),
        ]);

        DeadLetter::create([
            'business_id' => $business->id,
            'event_log_id' => $eventLog->id,
            'subscription_id' => null,
            'error_message' => self::MARKER.'Mailbox rejected the message',
            'attempts' => 10,
            'notified_at' => now(),
        ]);

        return 2;
    }

    public function purge(Business $business): int
    {
        $deadLettersCount = DeadLetter::where('business_id', $business->id)
            ->where('error_message', 'like', self::MARKER.'%')
            ->delete();

        EventLog::where('business_id', $business->id)
            ->where('event_name', 'like', self::MARKER.'%')
            ->delete();

        return $deadLettersCount;
    }
}
