<?php

declare(strict_types=1);

namespace App\Modules\X124\Actions;

final class AssistantUndoAction
{
    public function handle(int $businessId, string $actionKey): array
    {
        return [
            'status' => 'undone',
            'action_key' => $actionKey,
        ];
    }
}
