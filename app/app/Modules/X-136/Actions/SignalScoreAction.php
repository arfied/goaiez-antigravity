<?php

declare(strict_types=1);

namespace App\Modules\X136\Actions;

use App\Modules\X136\Events\IntentHigh;
use App\Modules\X136\Events\SignalDetected;
use App\Modules\X136\Models\Signal;
use App\Modules\X136\Models\SignalScore;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Facades\Event;

final class SignalScoreAction
{
    public const HIGH_INTENT_SCORE = 75.0;

    /**
     * Scores prospect intent signal.
     * 1. 1,000 high-intent signals produce 1,000 alert/list rows and zero permits (TEST ANCHOR).
     * 2. An alert, never a direct transport dispatch (P-068).
     */
    private function highIntentScore(): float
    {
        return $this->registry->float('signals.high_intent_score');
    }

    public function __construct(private DefaultsRegistry $registry) {}

    public function recordAndScore(
        int $businessId,
        string $prospectIdentifier,
        string $signalType,
        array $payload,
        float $baseScore
    ): SignalScore {
        $signal = Signal::create([
            'business_id' => $businessId,
            'prospect_identifier' => $prospectIdentifier,
            'signal_type' => $signalType,
            'payload' => $payload,
        ]);

        Event::dispatch(new SignalDetected($businessId, $signal->id, $prospectIdentifier, $signalType));

        $isHighIntent = ($baseScore >= $this->highIntentScore());
        $coolingStatus = $isHighIntent ? 'fresh' : 'cooling';

        $score = SignalScore::create([
            'business_id' => $businessId,
            'signal_id' => $signal->id,
            'prospect_identifier' => $prospectIdentifier,
            'signal_value' => $baseScore,
            'is_high_intent' => $isHighIntent,
            'cooling_status' => $coolingStatus,
        ]);

        if ($isHighIntent) {
            Event::dispatch(new IntentHigh($businessId, $score->id, $prospectIdentifier, $baseScore));
        }

        return $score;
    }
}
