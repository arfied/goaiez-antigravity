<?php

declare(strict_types=1);

namespace App\Modules\X102\Actions;

use App\Modules\X102\Events\ChatTurnCreated;
use App\Modules\X102\Models\ChatTurn;
use Illuminate\Support\Facades\Event;

final class ChatTurnAction
{
    public function handle(int $businessId, int $chatSessionId, string $authorType, string $message): ChatTurn
    {
        $turn = ChatTurn::create([
            'business_id' => $businessId,
            'chat_session_id' => $chatSessionId,
            'author_type' => $authorType,
            'message' => $message,
        ]);

        Event::dispatch(new ChatTurnCreated(
            businessId: $businessId,
            turnId: $turn->id,
        ));

        // TODO(X-102, C-Agent):
        // 1. $authorType reaches the wire unread. This is a defect: we should only call C-Agent if the author is 'visitor'.
        // 2. $conversationId is not passed. This is a build owed by Track 1: mapping chat_session_id to C-Agent's conversation_id so HUMAN_TAKEOVER_LATCH works is required, but it has not been asked for yet.
        // 3. $turnNumber defaults to 1. This is a build owed: X-102 should compute and pass the real turn number.

        app('App\Modules\CAgent\Actions\AgentAnswerAction')->handle($businessId, $message);

        return $turn;
    }
}
