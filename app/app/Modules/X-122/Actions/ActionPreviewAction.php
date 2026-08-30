<?php

declare(strict_types=1);

namespace App\Modules\X122\Actions;

use App\Modules\X122\Models\ActionManifest;

final class ActionPreviewAction
{
    public function handle(int $businessId, string $actionName, array $parameters): array
    {
        $manifest = ActionManifest::where('business_id', $businessId)
            ->where('action_name', $actionName)
            ->first();

        if ($manifest === null) {
            return [
                'status' => 'unsupported',
                'message' => "Action {$actionName} is not registered",
            ];
        }

        return [
            'status' => 'preview',
            'action' => $actionName,
            'is_reversible' => $manifest->is_reversible,
            'reversal_action' => $manifest->reversal_action,
            'simulated_effect' => 'Parameters valid, ready for dispatch',
        ];
    }
}
