<?php

declare(strict_types=1);

namespace App\Modules\X125\Actions;

use App\Modules\X125\Events\FlowChanged;
use App\Modules\X125\Models\FlowVersion;
use Illuminate\Support\Facades\Event;

final class FlowRollbackAction
{
    public function handle(int $businessId, int $flowId, int $targetVersionNumber): FlowVersion
    {
        $targetVersion = FlowVersion::where('business_id', $businessId)
            ->where('flow_id', $flowId)
            ->where('version_number', $targetVersionNumber)
            ->firstOrFail();

        Event::dispatch(new FlowChanged($businessId, $flowId, 'rolled_back'));

        return $targetVersion;
    }
}
