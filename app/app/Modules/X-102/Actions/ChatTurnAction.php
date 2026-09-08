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

        return $turn;
    }
}
