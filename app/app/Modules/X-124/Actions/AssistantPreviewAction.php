<?php

declare(strict_types=1);

namespace App\Modules\X124\Actions;

final class AssistantPreviewAction
{
    public function handle(int $businessId, string $actionKey, array $params = []): array
    {
        return [
            'status' => 'preview_ready',
            'action_key' => $actionKey,
            'projected_changes' => "Will execute {$actionKey} with given parameters",
            'is_irreversible' => in_array($actionKey, ['delete_tenant', 'refund_charge', 'bulk_delete']),
        ];
    }
}
