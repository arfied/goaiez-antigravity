<?php

declare(strict_types=1);

namespace App\Modules\X184\Actions;

use App\Modules\X184\Events\PlanCreated;
use App\Modules\X184\Models\ContentPlan;
use App\Modules\X184\Models\PlanItem;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class PlanProposeAction
{
    /**
     * Proposes weekly multi-channel content plan (G12-36, G9-16).
     * Every plan item names its source event (TEST ANCHOR).
     */
    public function proposePlan(
        int $businessId,
        string $weekLabel,
        int $postsCadence = 3,
        array $items = []
    ): ContentPlan {
        $plan = ContentPlan::create([
            'business_id' => $businessId,
            'week_label' => $weekLabel,
            'posts_per_week_cadence' => $postsCadence,
            'is_cadence_approved' => false,
        ]);

        foreach ($items as $item) {
            $sourceEvent = $item['source_event'] ?? '';
            // TEST ANCHOR: Every plan item names its source event
            if (empty($sourceEvent)) {
                throw new InvalidArgumentException('Plan item rejected: every plan item must name its source event (TEST ANCHOR)');
            }

            PlanItem::create([
                'business_id' => $businessId,
                'plan_id' => $plan->id,
                'channel' => $item['channel'] ?? 'facebook',
                'topic_theme' => $item['topic_theme'] ?? 'Seasonal HVAC Tune-Up',
                'source_event' => $sourceEvent,
                'scheduled_date' => $item['scheduled_date'] ?? now()->toDateString(),
                'is_scheduled' => false,
            ]);
        }

        Event::dispatch(new PlanCreated($businessId, $plan->id, $weekLabel));

        return $plan;
    }
}
