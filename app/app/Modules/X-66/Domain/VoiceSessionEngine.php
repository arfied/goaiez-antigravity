<?php

declare(strict_types=1);

namespace App\Modules\X66\Domain;

use App\Modules\X66\Events\CallAnswered;
use App\Modules\X66\Events\CallMissed;
use App\Modules\X66\Events\CallRinging;
use App\Modules\X66\Events\SentimentNegative;
use App\Modules\X66\Events\VoicemailTranscribed;
use App\Modules\X66\Models\CallAutopsy;
use App\Modules\X66\Models\CallSession;
use App\Modules\X66\Models\CallTurn;
use App\Modules\X66\Models\Voicemail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class VoiceSessionEngine
{
    /**
     * Ingest ring event immediately (TEST ANCHOR: ring event alone produces text-back).
     */
    public function handleRing(int $businessId, string $callSid, string $fromPhone, string $toPhone): CallSession
    {
        return DB::transaction(function () use ($businessId, $callSid, $fromPhone, $toPhone) {
            $session = CallSession::create([
                'business_id' => $businessId,
                'call_sid' => $callSid,
                'from_phone' => $fromPhone,
                'to_phone' => $toPhone,
                'status' => 'ringing',
                'latency_ms' => 300,
                'fallback_triggered' => false,
            ]);

            Event::dispatch(new CallRinging(
                businessId: $businessId,
                sessionId: $session->id,
                callSid: $callSid,
                fromPhone: $fromPhone,
                toPhone: $toPhone
            ));

            return $session;
        });
    }

    /**
     * Answer call with latency tracking and dynamic fallback (TEST ANCHOR).
     */
    public function handleAnswer(int $businessId, int $sessionId, int $latencyMs = 350): array
    {
        return DB::transaction(function () use ($businessId, $sessionId, $latencyMs) {
            $session = CallSession::where('business_id', $businessId)->findOrFail($sessionId);

            $fallbackTriggered = $latencyMs > 600;

            $session->update([
                'status' => 'answered',
                'latency_ms' => $latencyMs,
                'fallback_triggered' => $fallbackTriggered,
            ]);

            Event::dispatch(new CallAnswered(
                businessId: $businessId,
                sessionId: $session->id,
                fromPhone: $session->from_phone
            ));

            return [
                'session_id' => $session->id,
                'status' => 'answered',
                'latency_ms' => $latencyMs,
                'fallback_triggered' => $fallbackTriggered,
                'route' => $fallbackTriggered ? 'fallback_audio_stream' : 'primary_ultra_low_latency',
            ];
        });
    }

    /**
     * Record a call turn with barge-in interruption latency (TEST ANCHOR).
     */
    public function recordTurn(
        int $businessId,
        int $sessionId,
        int $turnIndex,
        string $speaker,
        string $transcript,
        int $bargeInLatencyMs = 120
    ): CallTurn {
        return CallTurn::create([
            'business_id' => $businessId,
            'session_id' => $sessionId,
            'turn_index' => $turnIndex,
            'speaker' => $speaker,
            'transcript' => $transcript,
            'barge_in_latency_ms' => $bargeInLatencyMs,
        ]);
    }

    public function handleMissed(int $businessId, int $sessionId): CallSession
    {
        $session = CallSession::where('business_id', $businessId)->findOrFail($sessionId);
        $session->update(['status' => 'missed']);

        Event::dispatch(new CallMissed(
            businessId: $businessId,
            sessionId: $session->id,
            fromPhone: $session->from_phone,
            toPhone: $session->to_phone
        ));

        return $session;
    }

    public function handleVoicemail(int $businessId, int $sessionId, string $audioUrl, string $transcription): Voicemail
    {
        $vm = Voicemail::create([
            'business_id' => $businessId,
            'call_session_id' => $sessionId,
            'audio_url' => $audioUrl,
            'transcription' => $transcription,
            'duration_seconds' => 30,
        ]);

        Event::dispatch(new VoicemailTranscribed(
            businessId: $businessId,
            voicemailId: $vm->id,
            transcription: $transcription
        ));

        return $vm;
    }

    public function coach(int $businessId, int $sessionId, string $transcript): CallAutopsy
    {
        $hasObjection = str_contains(strtolower($transcript), 'too expensive') || str_contains(strtolower($transcript), 'competitor');
        $sentiment = $hasObjection ? 'negative' : 'positive';

        if ($sentiment === 'negative') {
            Event::dispatch(new SentimentNegative(
                businessId: $businessId,
                sessionId: $sessionId,
                reason: 'Price objection detected'
            ));
        }

        return CallAutopsy::create([
            'business_id' => $businessId,
            'call_session_id' => $sessionId,
            'metrics' => ['objection_count' => $hasObjection ? 1 : 0],
            'sentiment' => $sentiment,
            'coaching_notes' => $hasObjection ? 'Reinforce value proposition before price discussion' : 'Good rapport',
        ]);
    }
}
