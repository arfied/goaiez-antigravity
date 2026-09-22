<?php

declare(strict_types=1);

namespace App\Modules\X125\Actions;

use App\Modules\X125\Events\FlowChanged;
use App\Modules\X125\Models\Flow;
use Illuminate\Support\Facades\Event;

final class FlowResumeAction
{
    public function handle(int $businessId, int $flowId): Flow
    {
        $flow = Flow::where('business_id', $businessId)->findOrFail($flowId);
        $flow->update(['status' => 'active', 'consecutive_errors' => 0]);

        Event::dispatch(new FlowChanged($businessId, $flow->id, 'active'));

        return $flow;
    }
}
