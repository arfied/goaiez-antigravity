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
        abort_if($deployment->page_variant_id !== null, 422, 'A test arm is stopped from Build my site, not rolled back.');

        $wasLive = $deployment->status === 'deployed';

        $deployment->update([
            'status' => 'rolled_back',
            'rollback_reason' => $reason,
        ]);

        // (R245, 2026-09-05) a rollback restores only the previous deploy of the same page.
        if ($wasLive) {
            $predecessor = Deployment::where('business_id', $businessId)
                ->where('edge_zone_id', $deployment->edge_zone_id)
                ->where('page_id', $deployment->page_id)
                ->where('status', 'superseded')
                ->whereNull('page_variant_id')
                ->orderByDesc('id')
                ->first();

            if ($predecessor) {
                $predecessor->update(['status' => 'deployed']);
            }
        }

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
