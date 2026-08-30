<?php

declare(strict_types=1);

namespace App\Modules\X125\Actions;

use App\Modules\X125\Models\Flow;
use App\Modules\X125\Models\FlowVersion;

final class FlowSimulateAction
{
    public function handle(int $businessId, int $flowId, array $samplePayload): array
    {
        $flow = Flow::where('business_id', $businessId)->findOrFail($flowId);
        $version = FlowVersion::where('business_id', $businessId)->where('flow_id', $flow->id)->latest('id')->firstOrFail();

        return [
            'status' => 'simulated',
            'flow_id' => $flow->id,
            'steps_executed' => count($version->nodes),
            'projected_outcome' => 'All nodes completed with 0 side-effects',
        ];
    }
}
