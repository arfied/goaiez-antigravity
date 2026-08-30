<?php

declare(strict_types=1);

namespace App\Modules\X01\Actions;

use App\Modules\X121\Models\Conversation;

final class ConversationReadAction
{
    public function handle(int $businessId, int $conversationId): ?Conversation
    {
        return Conversation::where('business_id', $businessId)->find($conversationId);
    }
}
