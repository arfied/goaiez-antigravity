<?php

declare(strict_types=1);

namespace App\Modules\X122\Actions;

use App\Modules\X122\Models\ActionManifest;

final class ManifestRegisterAction
{
    public function handle(
        int $businessId,
        string $actionName,
        array $schema,
        ?string $reversalAction = null,
        bool $isReversible = false
    ): ActionManifest {
        return ActionManifest::updateOrCreate(
            ['business_id' => $businessId, 'action_name' => $actionName],
            [
                'schema' => $schema,
                'reversal_action' => $reversalAction,
                'is_reversible' => $isReversible,
            ]
        );
    }
}
