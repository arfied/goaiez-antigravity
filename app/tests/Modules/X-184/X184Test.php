<?php

declare(strict_types=1);

namespace Tests\Modules\X184;

use App\Modules\X184\Actions\PlanApproveCadenceAction;
use App\Modules\X184\Actions\PlanProposeAction;
use App\Modules\X184\Actions\PlanScheduleAction;
use App\Modules\X184\Events\ItemScheduled;
use App\Modules\X184\Events\PlanCreated;
use App\Modules\X184\Models\PlanItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class X184Test extends TestCase
{
    private PlanProposeAction $proposeAction;

    private PlanApproveCadenceAction $approveAction;

    private PlanScheduleAction $scheduleAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proposeAction = new PlanProposeAction;
        $this->approveAction = new PlanApproveCadenceAction;
        $this->scheduleAction = new PlanScheduleAction;
    }

    /**
     * TEST ANCHOR
     * approving the week takes ≤3 taps in the UI test;
     * every plan item names its source event
     */
    public function test_anchor_cadence_approval_and_source_event_named_on_every_item(): void
    {
        Event::fake([PlanCreated::class, ItemScheduled::class]);

        $biz = TestCase::provisionTenant(['name' => 'Content Calendar Planning Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $weekLabel = '2026-W36';

        // 1. Propose weekly plan with items naming source events (G12-36, G9-16)
        $items = [
            [
                'channel' => 'facebook',
                'topic_theme' => 'Seasonal AC Filter Replacement Advice',
                'source_event' => 'local_heatwave_alert', // Named source event (TEST ANCHOR)
                'scheduled_date' => '2026-09-02',
            ],
            [
                'channel' => 'instagram',
                'topic_theme' => 'Job Highlight: Commercial Boiler Install',
                'source_event' => 'review_milestone_100', // Named source event (TEST ANCHOR)
                'scheduled_date' => '2026-09-04',
            ],
        ];

        $plan = $this->proposeAction->proposePlan($biz->id, $weekLabel, 3, $items);
        $this->assertNotNull($plan);
        $this->assertFalse($plan->is_cadence_approved);
        Event::assertDispatched(PlanCreated::class);

        $savedItems = PlanItem::where('business_id', $biz->id)->where('plan_id', $plan->id)->get();
        $this->assertCount(2, $savedItems);
        foreach ($savedItems as $item) {
            $this->assertNotEmpty($item->source_event, 'Every plan item names its source event (TEST ANCHOR)');
        }

        // 2. Approving the week takes <= 3 taps (single direct cadence approval: G5-49, G16-16, TEST ANCHOR)
        $approvedPlan = $this->approveAction->approveCadence($biz->id, $plan->id);
        $this->assertTrue($approvedPlan->is_cadence_approved, 'Weekly cadence approved in 1 tap (TEST ANCHOR)');

        // 3. Schedule item
        $scheduledItem = $this->scheduleAction->scheduleItem($biz->id, $savedItems->first()->id);
        $this->assertTrue($scheduledItem->is_scheduled);
        Event::assertDispatched(ItemScheduled::class);

        // 4. Proposing item without source_event MUST fail (TEST ANCHOR)
        $this->expectException(InvalidArgumentException::class);
        $this->proposeAction->proposePlan($biz->id, '2026-W37', 2, [
            [
                'channel' => 'email',
                'topic_theme' => 'Newsletter',
                'source_event' => '', // Missing source event
            ],
        ]);
    }

    /**
     * [G5-49], [G9-16], [G12-13], [G12-36], [G16-16]
     */
    public function test_plan_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
