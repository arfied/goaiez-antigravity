<?php

declare(strict_types=1);

namespace App\Modules\X01\Actions;

use App\Modules\X01\Domain\UnifiedInboxManager;

final class ConversationTakeoverAction
{
    public function __construct(private readonly UnifiedInboxManager $manager) {}

    public function handle(int $businessId, int $conversationId, int $operatorId, string $operatorName): array
    {
        return $this->manager->takeover($businessId, $conversationId, $operatorId, $operatorName);
    }
}
