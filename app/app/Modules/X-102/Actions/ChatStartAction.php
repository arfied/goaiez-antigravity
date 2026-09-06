<?php

declare(strict_types=1);

namespace App\Modules\X102\Actions;

use App\Modules\X102\Events\ChatStarted;
use App\Modules\X102\Models\ChatSession;
use App\Modules\X110\Domain\PixelEngine;
use App\Services\Ai\AiSpend;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

final class ChatStartAction
{
    private PixelEngine $pixelEngine;

    private AiSpend $aiSpend;

    public function __construct(?PixelEngine $pixelEngine = null, ?AiSpend $aiSpend = null)
    {
        $this->pixelEngine = $pixelEngine ?? app(PixelEngine::class);
        $this->aiSpend = $aiSpend ?? app(AiSpend::class);
    }

    public function handle(int $businessId, ?string $visitorIp = null, ?bool $isAiCapped = null, ?string $pixelSessionToken = null): ChatSession
    {
        $capped = $isAiCapped ?? ! $this->aiSpend->allows();
        $pageContext = $pixelSessionToken ? $this->pixelEngine->pageContextForSession($businessId, $pixelSessionToken) : null;

        $session = ChatSession::create([
            'business_id' => $businessId,
            'session_token' => 'chat_sess_'.Str::random(16),
            'visitor_ip' => $visitorIp,
            'status' => $capped ? 'offline_form' : 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => $capped,
            'pixel_session_token' => $pixelSessionToken,
            'page_context' => $pageContext,
        ]);

        Event::dispatch(new ChatStarted(
            businessId: $businessId,
            sessionId: $session->id,
            sessionToken: $session->session_token,
            isAiCapped: $capped
        ));

        return $session;
    }
}
