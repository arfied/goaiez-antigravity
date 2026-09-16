<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X184\Models\ContentPlan;
use App\Modules\X184\Models\PlanItem;

class X184Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-184';
    }

    public function fill(Business $business): int
    {
        if (ContentPlan::where('business_id', $business->id)->where('week_label', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $plan = ContentPlan::create(['business_id' => $business->id, 'week_label' => self::MARKER.'Plan', 'posts_per_week_cadence' => 2]);
        
        PlanItem::create(['business_id' => $business->id, 'plan_id' => $plan->id, 'channel' => self::MARKER.'Chan1', 'scheduled_date' => now(), 'topic_theme' => 'demo·Theme', 'source_event' => 'demo·Event']);
        PlanItem::create(['business_id' => $business->id, 'plan_id' => $plan->id, 'channel' => self::MARKER.'Chan2', 'scheduled_date' => now(), 'topic_theme' => 'demo·Theme', 'source_event' => 'demo·Event']);

        return 3;
    }

    public function purge(Business $business): int
    {
        $count = PlanItem::where('business_id', $business->id)->where('channel', 'like', self::MARKER.'%')->delete();
        $count += ContentPlan::where('business_id', $business->id)->where('week_label', 'like', self::MARKER.'%')->delete();
        return $count;
    }
}
