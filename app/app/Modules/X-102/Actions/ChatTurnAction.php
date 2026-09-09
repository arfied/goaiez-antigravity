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
        // BUILD PROPOSAL: X-01 cannot listen to ChatTurnCreated and use ingestMessage because web visitors only have a session token, which ingestMessage would wrongly insert into the Person phone column since it lacks an '@'. Owner: X-01
        // BUILD PROPOSAL: ChatTurnCreated carries no message text, and passing it would risk the AgentTurns law against unencrypted PII in generalized event payloads if queued. X-102 needs a safe cross-module retrieval method. Owner: X-102
        app('App\Modules\CAgent\Actions\AgentAnswerAction')->handle($businessId, $message);

        return $turn;
    }
}
