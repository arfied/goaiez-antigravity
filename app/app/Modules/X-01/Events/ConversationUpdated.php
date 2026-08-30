<?php

declare(strict_types=1);

namespace App\Modules\X01\Events;

final class ConversationUpdated
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $conversationId,
        public readonly string $channel,
        public readonly string $messageSnippet
    ) {}
}
