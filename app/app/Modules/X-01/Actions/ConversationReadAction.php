<?php

declare(strict_types=1);

namespace App\Modules\X01\Actions;

use App\Models\Conversation;

final class ConversationReadAction
{
    public function handle(int $businessId, int $conversationId): ?Conversation
    {
        return \App\Support\Tenancy::actingAs($businessId, function () use ($businessId, $conversationId) {
            return Conversation::where('business_id', $businessId)->find($conversationId);
        });
    }
}
