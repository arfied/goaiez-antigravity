<?php

declare(strict_types=1);

namespace App\Modules\X102\Actions;

use App\Modules\X102\Events\ChatEscalated;
use App\Modules\X102\Models\ChatSession;
use Illuminate\Support\Facades\Event;

final class ChatEscalateAction
{
    public function handle(int $businessId, int $sessionId, string $reason): array
    {
        $session = ChatSession::where('business_id', $businessId)->findOrFail($sessionId);
        $session->update(['status' => 'escalated']);

        Event::dispatch(new ChatEscalated(
            businessId: $businessId,
            sessionId: $session->id,
            reason: $reason
        ));

        return [
            'status' => 'escalated',
            'session_id' => $session->id,
            'reason' => $reason,
        ];
    }

    /**
     * Track rage click: 4 rage clicks escalate (TEST ANCHOR).
     */
    public function recordRageClick(int $businessId, int $sessionId): array
    {
        $session = ChatSession::where('business_id', $businessId)->findOrFail($sessionId);
        $session->increment('rage_clicks_count');

        if ($session->rage_clicks_count >= 4) {
            return $this->handle($businessId, $sessionId, 'four_rage_clicks_detected');
        }

        return [
            'status' => 'rage_click_recorded',
            'count' => $session->rage_clicks_count,
        ];
    }

    /**
     * Grounded FAQ answer resolution (TEST ANCHOR: no grounding Fact gets refusal string).
     */
    public function answerQuestion(int $businessId, string $question, ?string $groundingFact = null): array
    {
        if (empty($groundingFact)) {
            return [
                'status' => 'refused',
                'refusal_code' => 'NO_GROUNDING_FACT',
                'answer' => 'I do not have verified business details for that question. Let me connect you with our team.',
            ];
        }

        return [
            'status' => 'answered',
            'answer' => "Based on verified facts: {$groundingFact}",
        ];
    }
}
