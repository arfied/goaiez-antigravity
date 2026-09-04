<?php

declare(strict_types=1);

namespace Tests\Modules\X125;

use App\Modules\X125\Actions\FlowCreateAction;
use App\Modules\X125\Actions\FlowExplainAction;
use App\Modules\X125\Actions\FlowRunAction;
use App\Modules\X125\Actions\FlowSimulateAction;
use App\Modules\X125\Events\FlowChanged;
use App\Modules\X125\Models\Flow;
use App\Modules\X125\Models\FlowVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X125Test extends TestCase
{
    private FlowCreateAction $createAction;

    private FlowRunAction $runAction;

    private FlowExplainAction $explainAction;

    private FlowSimulateAction $simulateAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAction = new FlowCreateAction;
        $this->runAction = new FlowRunAction;
        $this->explainAction = new FlowExplainAction;
        $this->simulateAction = new FlowSimulateAction;
    }

    /**
     * TEST ANCHOR
     * a new tenant on the plumber profile has ≥1 flow running before their first login;
     * a flow paused after N errors resumes on the next successful manual run and never silently retries;
     * flow.explain returns the flow in plain words that match its nodes
     */
    public function test_anchor_plumber_default_flow_error_pause_and_plain_explain(): void
    {
        Event::fake([FlowChanged::class]);

        $biz = TestCase::provisionTenant(['name' => 'Plumber Flow Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Plumber profile default active flow (TEST ANCHOR: >= 1 flow running before first login)
        $plumberFlowNodes = [
            ['type' => 'trigger', 'label' => 'Inbound call missed'],
            ['type' => 'action', 'label' => 'Send SMS auto-reply'],
            ['type' => 'action', 'label' => 'Create CRM deal lead'],
        ];

        $flow = $this->createAction->handle(
            businessId: $biz->id,
            name: 'Missed Call Recovery Flow',
            triggerEvent: 'call.missed',
            nodes: $plumberFlowNodes,
            maxErrorThreshold: 2 // Pauses after 2 consecutive errors
        );

        $this->assertEquals('active', $flow->status);
        $this->assertTrue($flow->is_active);
        $this->assertGreaterThanOrEqual(1, Flow::where('business_id', $biz->id)->where('status', 'active')->count());

        // 2. Flow explain matches nodes in plain words (TEST ANCHOR)
        $explanation = $this->explainAction->explain('call.missed', $plumberFlowNodes);
        $this->assertStringContainsString("When 'call.missed' event occurs", $explanation);
        $this->assertStringContainsString('Send SMS auto-reply', $explanation);
        $this->assertStringContainsString('Create CRM deal lead', $explanation);

        $version = FlowVersion::where('business_id', $biz->id)->where('flow_id', $flow->id)->first();
        $this->assertEquals($explanation, $version->plain_explanation);

        // 3. Error handling: flow fails twice -> transitions to paused_error
        $this->runAction->handle($biz->id, $flow->id, ['caller' => '+15551112222'], isManualRetry: false, shouldSimulateFailure: true);
        $this->runAction->handle($biz->id, $flow->id, ['caller' => '+15551112222'], isManualRetry: false, shouldSimulateFailure: true);

        $pausedFlow = Flow::where('business_id', $biz->id)->find($flow->id);
        $this->assertEquals('paused_error', $pausedFlow->status);
        $this->assertEquals(2, $pausedFlow->consecutive_errors);

        // 4. Automatic background run is REFUSED (never silently retries: TEST ANCHOR)
        $silentRetryRes = $this->runAction->handle($biz->id, $flow->id, ['caller' => '+15551112222'], isManualRetry: false);
        $this->assertEquals('refused_paused', $silentRetryRes['status']);
        $this->assertEquals('FLOW_PAUSED_AFTER_ERRORS_NO_SILENT_RETRY', $silentRetryRes['refusal_code']);

        // 5. Manual run resumes the paused flow (TEST ANCHOR)
        $manualResumeRes = $this->runAction->handle($biz->id, $flow->id, ['caller' => '+15551112222'], isManualRetry: true, shouldSimulateFailure: false);
        $this->assertEquals('success', $manualResumeRes['status']);
        $this->assertEquals('active', $manualResumeRes['flow_status']);

        $resumedFlow = Flow::where('business_id', $biz->id)->find($flow->id);
        $this->assertEquals('active', $resumedFlow->status);
        $this->assertEquals(0, $resumedFlow->consecutive_errors);
    }

    /**
     * [G2-22] stage change fires flow; 40-step template is a canvas
     */
    public function test_g2_22_canvas_and_simulation(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Canvas Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $flow = $this->createAction->handle(
            businessId: $biz->id,
            name: 'Opportunity Stage Change Flow',
            triggerEvent: 'deal.stage_changed',
            nodes: [
                ['type' => 'filter', 'label' => 'Stage equals Closed Won'],
                ['type' => 'action', 'label' => 'Request review via SMS'],
            ]
        );

        $simRes = $this->simulateAction->handle($biz->id, $flow->id, ['new_stage' => 'Closed Won']);
        $this->assertEquals('simulated', $simRes['status']);
        $this->assertEquals(2, $simRes['steps_executed']);
    }

    public function test_runs_component_renders_and_handles_retry(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Runs Component Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $flow = $this->createAction->handle(
            businessId: $biz->id,
            name: 'Test Flow Runs',
            triggerEvent: 'test.event',
            nodes: [['type' => 'action', 'label' => 'Test Action']],
        );

        $initialRunsCount = \App\Modules\X125\Models\FlowRun::where('business_id', $biz->id)->count();

        $this->runAction->handle($biz->id, $flow->id, ['test' => true]);

        $component = \Livewire\Livewire::test(\App\Modules\X125\Ui\Runs::class, ['businessId' => $biz->id])
            ->call('load')
            ->assertSee('Test Flow Runs')
            ->assertSee("When 'test.event' event occurs")
            ->assertSee('Test Action');

        $latestRun = \App\Modules\X125\Models\FlowRun::where('business_id', $biz->id)->orderByDesc('id')->first();
        $component->call('retry', $latestRun->id);

        $newCount = \App\Modules\X125\Models\FlowRun::where('business_id', $biz->id)->count();
        $this->assertEquals($initialRunsCount + 2, $newCount);

        $newRun = \App\Modules\X125\Models\FlowRun::where('business_id', $biz->id)->orderByDesc('id')->first();
        $this->assertTrue($newRun->is_manual_retry, 'Retry must be marked as manual');
    }
}
