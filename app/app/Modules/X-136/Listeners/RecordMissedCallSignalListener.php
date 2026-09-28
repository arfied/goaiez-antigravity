<?php

declare(strict_types=1);

namespace App\Modules\X136\Listeners;

use App\Events\Voice\CallMissed;
use App\Modules\X136\Actions\SignalScoreAction;
use App\Modules\X136\Models\Signal;

final class RecordMissedCallSignalListener
{
    public const SIGNAL_TYPE = 'call.missed';

    public const BASE_SCORE = SignalScoreAction::HIGH_INTENT_SCORE;

    public function __construct(private readonly SignalScoreAction $score) {}

    public function handle(CallMissed $event): void
    {
        if (! $event->call->type->owesTextBack()) {
            return;
        }

        $prospect = $event->call->customerId !== null
            ? 'customer:'.$event->call->customerId
            : 'phone:'.$event->call->from;

        $already = Signal::query()
            ->where('business_id', $event->businessId)
            ->where('signal_type', self::SIGNAL_TYPE)
            ->where('payload->provider_call_id', $event->call->providerCallId)
            ->exists();

        if ($already) {
            return;
        }

        $this->score->recordAndScore(
            $event->businessId,
            $prospect,
            self::SIGNAL_TYPE,
            ['provider_call_id' => $event->call->providerCallId, 'from' => $event->call->from, 'to' => $event->call->to, 'occurred_at' => $event->call->occurredAt->toIso8601String()],
            self::BASE_SCORE,
        );
    }
}
