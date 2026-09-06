<?php

declare(strict_types=1);

namespace App\Modules\X01\Actions;

use App\Modules\X01\Domain\UnifiedInboxManager;

final class ConversationTakeoverReleaseAction
{
    public function __construct(private readonly UnifiedInboxManager $manager) {}

    public function handle(int $businessId, int $conversationId): array
    {
        return $this->manager->releaseTakeover($businessId, $conversationId);
    }
}
