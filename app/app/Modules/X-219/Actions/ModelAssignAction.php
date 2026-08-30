<?php

declare(strict_types=1);

namespace App\Modules\X219\Actions;

use App\Modules\X219\Events\RosterChanged;
use App\Modules\X219\Models\AiModel;
use App\Modules\X219\Models\AiModuleAssignment;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class ModelAssignAction
{
    /**
     * Assign models per module (R237: Backup must be a DIFFERENT vendor).
     */
    public function handle(
        int $businessId,
        string $targetModule,
        int $primaryModelId,
        int $backupModelId,
        ?int $complexModelId = null
    ): AiModuleAssignment {
        $primary = AiModel::findOrFail($primaryModelId);
        $backup = AiModel::findOrFail($backupModelId);

        if ($primary->provider_id === $backup->provider_id) {
            throw new InvalidArgumentException('R237: Backup model must belong to a different provider/vendor than primary');
        }

        $assignment = AiModuleAssignment::updateOrCreate(
            ['business_id' => $businessId, 'target_module' => $targetModule],
            [
                'primary_model_id' => $primaryModelId,
                'backup_model_id' => $backupModelId,
                'complex_model_id' => $complexModelId,
            ]
        );

        Event::dispatch(new RosterChanged(
            businessId: $businessId,
            targetModule: $targetModule,
            changeType: 'assigned'
        ));

        return $assignment;
    }
}
