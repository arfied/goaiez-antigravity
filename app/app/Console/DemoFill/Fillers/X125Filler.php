<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X125\Models\Flow;
use App\Modules\X125\Models\FlowVersion;
use App\Modules\X125\Models\FlowRun;

class X125Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-125';
    }

    public function fill(Business $business): int
    {
        if (Flow::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $flow = Flow::create(['business_id' => $business->id, 'name' => self::MARKER.'Flow', 'trigger_event' => 'test', 'status' => 'active']);
        $fv = FlowVersion::create(['business_id' => $business->id, 'flow_id' => $flow->id, 'version_number' => 1, 'nodes' => [], 'plain_explanation' => self::MARKER.'Expl']);
        
        FlowRun::create(['business_id' => $business->id, 'flow_id' => $flow->id, 'flow_version_id' => $fv->id, 'status' => 'error', 'trigger_payload' => []]);
        FlowRun::create(['business_id' => $business->id, 'flow_id' => $flow->id, 'flow_version_id' => $fv->id, 'status' => 'success', 'trigger_payload' => []]);

        return 4;
    }

    public function purge(Business $business): int
    {
        $count = FlowRun::where('business_id', $business->id)->whereHas('flow', fn($q) => $q->where('name', 'like', self::MARKER.'%'))->delete();
        $count += FlowVersion::where('business_id', $business->id)->where('plain_explanation', 'like', self::MARKER.'%')->delete();
        $count += Flow::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->delete();
        return $count;
    }
}
