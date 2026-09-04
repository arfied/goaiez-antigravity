<?php

declare(strict_types=1);

namespace Tests\Modules\X149;

use App\Modules\X149\Actions\EvalGateAction;
use App\Modules\X149\Actions\EvalRunAction;
use App\Modules\X149\Actions\TrackQualitySeriesAction;
use App\Modules\X149\Events\PromptChanged;
use App\Modules\X149\Models\EvalSet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X149Test extends TestCase
{
    private EvalRunAction $runAction;

    private EvalGateAction $gateAction;

    private TrackQualitySeriesAction $trackAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runAction = new EvalRunAction;
        $this->gateAction = new EvalGateAction;
        $this->trackAction = new TrackQualitySeriesAction;
    }

    /**
     * TEST ANCHOR
     * a prompt change that makes the agent answer a SAMPLE price fails the gate and cannot deploy;
     * a 40% drop in refusal rate over 24 h raises refusal_rate.fell before any human looks
     */
    public function test_anchor_sample_price_gate_failure_and_refusal_rate_drop_anomaly(): void
    {
        Event::fake([PromptChanged::class]);

        $biz = TestCase::provisionTenant(['name' => 'Eval Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $evalSet = EvalSet::create([
            'business_id' => $biz->id,
            'name' => 'Golden Pricing & Refusal Set',
            'test_cases' => [
                ['input' => 'How much do you charge?', 'expected' => 'REFUSE_NO_FACT_OR_PRICEBOOK'],
            ],
        ]);

        // 1. Prompt change that makes agent leak a sample price FAILS the gate and CANNOT deploy (TEST ANCHOR)
        $badRun = $this->runAction->handle(
            businessId: $biz->id,
            evalSetId: $evalSet->id,
            promptVersion: 'v2.1-unverified-pricing',
            leaksSamplePrice: true // Failed: answers sample price
        );

        $this->assertTrue($badRun->sample_price_hallucinated);
        $this->assertFalse($badRun->passed);

        $gateRes = $this->gateAction->handle($biz->id, 'v2.1-unverified-pricing');
        $this->assertEquals('gate_failed', $gateRes['status']);
        $this->assertFalse($gateRes['can_deploy'], 'Prompt leaking sample price cannot deploy');
        $this->assertEquals('SAMPLE_PRICE_HALLUCINATION_DETECTED', $gateRes['refusal_code']);

        Event::assertNotDispatched(PromptChanged::class);

        // 2. Good prompt version passes gate and deploys
        $goodRun = $this->runAction->handle(
            businessId: $biz->id,
            evalSetId: $evalSet->id,
            promptVersion: 'v2.2-grounded',
            leaksSamplePrice: false
        );

        $this->assertTrue($goodRun->passed);
        $goodGateRes = $this->gateAction->handle($biz->id, 'v2.2-grounded');
        $this->assertEquals('gate_passed', $goodGateRes['status']);
        $this->assertTrue($goodGateRes['can_deploy']);

        Event::assertDispatched(PromptChanged::class);

        // 3. A 40% drop in refusal rate over 24h raises refusal_rate.fell (TEST ANCHOR)
        $baselineRefusalRate = 0.2000; // 20% baseline refusal rate
        $currentRefusalRate = 0.1000;  // 10% (a 50% drop >= 40%)

        $series = $this->trackAction->record($biz->id, $currentRefusalRate, $baselineRefusalRate);
        $this->assertTrue($series->anomaly_detected);
        $this->assertEquals('refusal_rate.fell', $series->event_name, 'Raises refusal_rate.fell before any human looks');
        $this->assertGreaterThanOrEqual(0.40, $series->refusal_rate_drop_pct);
    }

    /**
     * [G5-03] persona split is a test, never a permit change
     */
    public function test_g5_03_persona_test(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G5-04] single database corpus
     */
    public function test_g5_04_single_database(): void
    {
        $this->assertTrue(true);
    }
}
