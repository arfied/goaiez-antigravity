<?php

namespace Tests\Modules\X184;

use App\Modules\X184\Actions\PlanApproveCadenceAction;
use App\Modules\X184\Actions\PlanProposeAction;
use App\Modules\X184\Domain\PlanEngine;
use App\Modules\X184\Events\ItemScheduled;
use App\Modules\X184\Events\PlanCreated;
use App\Modules\X184\Models\PlanItem;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X184Test extends TestCase
{
    public function test_capabilities()
    {
        $engine = new PlanEngine;
        $this->assertTrue($engine->approveCadenceOnly('cadence'));
        $this->assertFalse($engine->approveCadenceOnly('topic_list'));
        $this->assertTrue($engine->recommendNotAutoPost('recommend'));
        $this->assertTrue($engine->refreshOnFatigue(true));
    }

    /**
     * [G5-49]
     */
    public function test_g5_49_approves_cadence_never_topic_list()
    {
        $biz = TestCase::provisionTenant(['name' => 'Test Biz', 'currency' => 'USD']);

        Tenancy::actingAs($biz->id, function () use ($biz) {
            $proposeAction = new PlanProposeAction(app(DefaultsRegistry::class));
            $plan = $proposeAction->proposePlan($biz->id, '2026-W35', 3, [
                ['channel' => 'facebook', 'topic_theme' => 'Winter Promo', 'source_event' => 'Promo Launch'],
                ['channel' => 'instagram', 'topic_theme' => 'Summer Sale', 'source_event' => 'Seasonal'],
            ]);

            $approveAction = new PlanApproveCadenceAction;
            $approvedPlan = $approveAction->approveCadence($biz->id, $plan->id);

            $this->assertTrue($approvedPlan->is_cadence_approved);

            $items = PlanItem::where('plan_id', $plan->id)->orderBy('id')->get();
            $this->assertCount(2, $items);
            $this->assertEquals('Winter Promo', $items[0]->topic_theme);
            $this->assertEquals('Summer Sale', $items[1]->topic_theme);
        });
    }

    /**
     * [G16-16]
     */
    public function test_g16_16_approving_cadence_does_not_schedule_items()
    {
        $biz = TestCase::provisionTenant(['name' => 'Test Biz', 'currency' => 'USD']);

        Tenancy::actingAs($biz->id, function () use ($biz) {
            $proposeAction = new PlanProposeAction(app(DefaultsRegistry::class));
            $plan = $proposeAction->proposePlan($biz->id, '2026-W35', 3, [
                ['channel' => 'facebook', 'topic_theme' => 'Winter Promo', 'source_event' => 'Promo Launch'],
            ]);

            $approveAction = new PlanApproveCadenceAction;
            $approveAction->approveCadence($biz->id, $plan->id);

            $items = PlanItem::where('plan_id', $plan->id)->get();
            $this->assertCount(1, $items);
            $this->assertFalse((bool) $items[0]->is_scheduled);
        });
    }

    /**
     * [G12-13]
     */
    public function test_g12_13_proposing_plan_recommends_never_auto_posts()
    {
        $biz = TestCase::provisionTenant(['name' => 'Test Biz', 'currency' => 'USD']);

        Tenancy::actingAs($biz->id, function () use ($biz) {
            Event::fake();

            $proposeAction = new PlanProposeAction(app(DefaultsRegistry::class));
            $plan = $proposeAction->proposePlan($biz->id, '2026-W35', 3, [
                ['channel' => 'facebook', 'topic_theme' => 'T1', 'source_event' => 'E1'],
                ['channel' => 'instagram', 'topic_theme' => 'T2', 'source_event' => 'E2'],
                ['channel' => 'gbp', 'topic_theme' => 'T3', 'source_event' => 'E3'],
            ]);

            $items = PlanItem::where('plan_id', $plan->id)->get();
            $this->assertCount(3, $items);
            foreach ($items as $item) {
                $this->assertFalse((bool) $item->is_scheduled);
            }

            Event::assertDispatched(PlanCreated::class);
            Event::assertNotDispatched(ItemScheduled::class);
        });
    }

    /**
     * [G12-36]
     */
    public function test_g12_36_trend_proposes_topic_cadence_is_approved()
    {
        $biz = TestCase::provisionTenant(['name' => 'Test Biz', 'currency' => 'USD']);

        Tenancy::actingAs($biz->id, function () use ($biz) {
            $proposeAction = new PlanProposeAction(app(DefaultsRegistry::class));
            $plan = $proposeAction->proposePlan($biz->id, '2026-W35', 3, [
                ['channel' => 'facebook', 'topic_theme' => 'T1', 'source_event' => 'Trend1'],
                ['channel' => 'facebook', 'topic_theme' => 'T2', 'source_event' => 'Trend2'],
                ['channel' => 'facebook', 'topic_theme' => 'T3', 'source_event' => 'Trend3'],
                ['channel' => 'facebook', 'topic_theme' => 'T4', 'source_event' => 'Trend4'],
                ['channel' => 'facebook', 'topic_theme' => 'T5', 'source_event' => 'Trend5'],
            ]);

            $this->assertEquals(3, $plan->posts_per_week_cadence);

            $items = PlanItem::where('plan_id', $plan->id)->get();
            $this->assertCount(5, $items);
        });
    }

    /**
     * [G9-16]
     */
    public function test_g9_16_decay_proposes_refresh_never_auto_refreshed_above_floor()
    {
        $engine = new PlanEngine;

        // fatigued and below floor -> refresh proposed
        $this->assertTrue($engine->autoRefreshAllowed(true, 100, 200));

        // fatigued and above floor -> not auto-refreshed
        $this->assertFalse($engine->autoRefreshAllowed(true, 250, 200));

        // fatigued and AT the floor -> not auto-refreshed
        $this->assertFalse($engine->autoRefreshAllowed(true, 200, 200));

        // not fatigued -> no refresh
        $this->assertFalse($engine->autoRefreshAllowed(false, 100, 200));
    }
}
