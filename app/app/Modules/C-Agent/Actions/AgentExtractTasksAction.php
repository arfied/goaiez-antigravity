<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Actions;

final class AgentExtractTasksAction
{
    public function handle(int $businessId, string $conversationText): array
    {
        return [
            ['task' => 'Follow up on quote', 'priority' => 'normal'],
        ];
    }
}
