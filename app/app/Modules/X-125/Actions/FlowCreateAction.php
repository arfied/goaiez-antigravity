<?php

declare(strict_types=1);

namespace App\Modules\X125\Actions;

use App\Modules\X125\Events\FlowChanged;
use App\Modules\X125\Models\Flow;
use App\Modules\X125\Models\FlowVersion;
use Illuminate\Support\Facades\Event;

final class FlowCreateAction
{
    public function __construct(private readonly FlowExplainAction $explainAction = new FlowExplainAction) {}

    public function handle(
        int $businessId,
        string $name,
        string $triggerEvent,
        array $nodes,
        int $maxErrorThreshold = 3
    ): Flow {
        $flow = Flow::create([
            'business_id' => $businessId,
            'name' => $name,
            'trigger_event' => $triggerEvent,
            'is_active' => true,
            'status' => 'active',
            'consecutive_errors' => 0,
            'max_error_threshold' => $maxErrorThreshold,
        ]);

        $plainExplanation = $this->explainAction->explain($triggerEvent, $nodes);

        FlowVersion::create([
            'business_id' => $businessId,
            'flow_id' => $flow->id,
            'version_number' => 1,
            'nodes' => $nodes,
            'plain_explanation' => $plainExplanation,
        ]);

        Event::dispatch(new FlowChanged($businessId, $flow->id, 'created'));

        return $flow;
    }
}
