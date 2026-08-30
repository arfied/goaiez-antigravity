<?php

declare(strict_types=1);

namespace App\Modules\X102\Actions;

use App\Modules\X102\Events\ChatStarted;
use App\Modules\X102\Models\ChatSession;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

final class ChatStartAction
{
    public function handle(int $businessId, ?string $visitorIp = null, bool $isAiCapped = false): ChatSession
    {
        $session = ChatSession::create([
            'business_id' => $businessId,
            'session_token' => 'chat_sess_'.Str::random(16),
            'visitor_ip' => $visitorIp,
            'status' => $isAiCapped ? 'offline_form' : 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => $isAiCapped,
        ]);

        Event::dispatch(new ChatStarted(
            businessId: $businessId,
            sessionId: $session->id,
            sessionToken: $session->session_token,
            isAiCapped: $isAiCapped
        ));

        return $session;
    }
}
