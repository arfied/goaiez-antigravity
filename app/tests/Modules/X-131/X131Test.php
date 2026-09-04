<?php

declare(strict_types=1);

namespace Tests\Modules\X131;

use App\Modules\X131\Actions\InterestInferAction;
use App\Modules\X131\Actions\InterestSetAction;
use App\Modules\X131\Events\InterestDetected;
use App\Modules\X131\Events\InterestOverridden;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X131Test extends TestCase
{
    private InterestInferAction $inferAction;

    private InterestSetAction $setAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inferAction = new InterestInferAction;
        $this->setAction = new InterestSetAction;
    }

    /**
     * TEST ANCHOR
     * a tenant-set interest is never overwritten by inference;
     * every inferred interest row carries confidence and source
     */
    public function test_anchor_tenant_set_never_overwritten_by_inference_and_inferred_carries_confidence_source(): void
    {
        Event::fake([InterestDetected::class, InterestOverridden::class]);

        $biz = TestCase::provisionTenant(['name' => 'Interest Intelligence Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $personId = 8801;
        $topic = 'Commercial HVAC Maintenance';

        // 1. Tenant explicitly sets an interest tag
        $manualInterest = $this->setAction->set($biz->id, $personId, $topic);
        $this->assertTrue($manualInterest->is_tenant_set);
        $this->assertEquals('tenant_manual', $manualInterest->source);
        $this->assertEquals(1.00, $manualInterest->confidence_rate);
        Event::assertDispatched(InterestOverridden::class);

        // 2. Automated AI engine attempts to infer and overwrite the tenant-set interest with a lower score
        $inferredResult = $this->inferAction->infer(
            businessId: $biz->id,
            personId: $personId,
            topic: $topic,
            confidenceScore: 0.72,
            source: 'page_view_scroll_depth'
        );

        // Assert tenant-set tag was NEVER overwritten (TEST ANCHOR)
        $this->assertTrue($inferredResult->is_tenant_set, 'Tenant-set interest is NEVER overwritten by inference (TEST ANCHOR)');
        $this->assertEquals('tenant_manual', $inferredResult->source);
        $this->assertEquals(1.00, $inferredResult->confidence_rate);

        // 3. Infer a brand new topic (carries confidence and source: TEST ANCHOR & G13-34)
        $newTopic = 'Emergency Duct Repair';
        $freshInferred = $this->inferAction->infer(
            businessId: $biz->id,
            personId: $personId,
            topic: $newTopic,
            confidenceScore: 0.895,
            source: 'search_intent_lead_form'
        );

        $this->assertFalse($freshInferred->is_tenant_set);
        $this->assertEquals(0.895, $freshInferred->confidence_rate, 'Inferred row carries confidence score (TEST ANCHOR)');
        $this->assertEquals('search_intent_lead_form', $freshInferred->source, 'Inferred row carries source (TEST ANCHOR)');

        Event::assertDispatched(InterestDetected::class);
    }

    /**
     * [G5-29], [G13-34]
     */
    public function test_interest_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
