<?php

declare(strict_types=1);

namespace App\Modules\X157\Actions;

use App\Modules\X157\Events\DeployRolledBack;
use App\Modules\X157\Models\Deployment;
use Illuminate\Support\Facades\Event;

final class EdgeRollbackAction
{
    public function handle(int $businessId, int $deploymentId, string $reason = 'manual_rollback'): array
    {
        $deployment = Deployment::where('business_id', $businessId)->findOrFail($deploymentId);
        $deployment->update([
            'status' => 'rolled_back',
            'rollback_reason' => $reason,
        ]);

        Event::dispatch(new DeployRolledBack(
            businessId: $businessId,
            deploymentId: $deployment->id,
            metric: 'manual_trigger',
            reason: $reason
        ));

        return [
            'deployment_id' => $deployment->id,
            'status' => 'rolled_back',
        ];
    }
}
