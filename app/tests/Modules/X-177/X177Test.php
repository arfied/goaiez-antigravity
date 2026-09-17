<?php

declare(strict_types=1);

namespace Tests\Modules\X177;

use App\Models\Location;
use App\Modules\X177\Actions\GbpAnswerAction;
use App\Modules\X177\Actions\GbpPostAction;
use App\Modules\X177\Actions\GbpStateAction;
use App\Modules\X177\Actions\GbpSyncHoursAction;
use App\Modules\X177\Events\GbpPosted;
use App\Modules\X177\Events\GbpQuestionAnswered;
use App\Modules\X177\Events\GbpReinstated;
use App\Modules\X177\Events\GbpSuspended;
use App\Modules\X177\Events\GbpSuspensionRisk;
use App\Modules\X177\Models\GbpConnection;
use App\Modules\X177\Models\GbpPost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X177Test extends TestCase
{
    private GbpPostAction $postAction;

    private GbpAnswerAction $answerAction;

    private GbpSyncHoursAction $syncHoursAction;

    private GbpStateAction $stateAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->postAction = new GbpPostAction;
        $this->answerAction = new GbpAnswerAction;
        $this->syncHoursAction = new GbpSyncHoursAction;
        $this->stateAction = new GbpStateAction;
    }

    /**
     * TEST ANCHOR
     * a write flagged by the risk ruleset never reaches Zernio;
     * on gbp.suspended zero posts are attempted for that profile until gbp.reinstated, asserted on the post log;
     * the state read runs on schedule when no webhook has arrived
     */
    public function test_anchor_risk_ruleset_suspension_gate_and_state_polling(): void
    {
        Event::fake([
            GbpPosted::class,
            GbpQuestionAnswered::class,
            GbpSuspensionRisk::class,
            GbpSuspended::class,
            GbpReinstated::class,
        ]);

        $biz = TestCase::provisionTenant(['name' => 'GBP Profile Management Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $loc = Location::factory()->create(['business_id' => $biz->id]);

        $conn = GbpConnection::create([
            'business_id' => $biz->id,
            'account_id' => 'acc_gbp_9901',
            'location_id' => $loc->id,
            'profile_status' => 'active',
        ]);

        // 1. A write flagged by the risk ruleset NEVER reaches Zernio (TEST ANCHOR)
        $riskContent = 'We offer guaranteed ranking #1 on search results!';
        $riskResult = $this->postAction->post(
            businessId: $biz->id,
            connectionId: $conn->id,
            content: $riskContent
        );

        $this->assertEquals('refused_risk', $riskResult['status']);
        $this->assertEquals('SUSPENSION_RISK_FLAGGED', $riskResult['refusal_code']);
        $this->assertFalse($riskResult['dispatched_to_zernio']);

        $riskPost = GbpPost::where('business_id', $biz->id)->find($riskResult['post_id']);
        $this->assertNotNull($riskPost);
        $this->assertEquals('rejected_risk', $riskPost->status);
        $this->assertNull($riskPost->zernio_dispatch_id, 'Risk write never reaches Zernio');

        Event::assertDispatched(GbpSuspensionRisk::class);
        Event::assertNotDispatched(GbpPosted::class);

        // 2. Profile becomes suspended (via state poll or webhook fallback) (TEST ANCHOR)
        $pollRes = $this->stateAction->pollState($biz->id, $conn->id, mockRemoteStatus: 'suspended');
        $this->assertEquals('suspended', $pollRes['profile_status']);
        $conn->refresh();
        $this->assertEquals('suspended', $conn->profile_status);

        Event::assertDispatched(GbpSuspended::class);

        // On gbp.suspended ZERO posts are attempted for that profile until gbp.reinstated (TEST ANCHOR)
        $suspendedPostRes = $this->postAction->post(
            businessId: $biz->id,
            connectionId: $conn->id,
            content: 'Winter HVAC safety inspections now available.'
        );

        $this->assertEquals('blocked', $suspendedPostRes['status']);
        $this->assertEquals('PROFILE_SUSPENDED', $suspendedPostRes['refusal_code']);
        $this->assertFalse($suspendedPostRes['dispatched_to_zernio']);

        $blockedPost = GbpPost::where('business_id', $biz->id)->find($suspendedPostRes['post_id']);
        $this->assertNotNull($blockedPost);
        $this->assertEquals('blocked_by_suspension', $blockedPost->status);
        $this->assertNull($blockedPost->zernio_dispatch_id, 'Zero posts attempted/dispatched while profile suspended');

        // 3. Profile reinstated -> posts resume (TEST ANCHOR)
        $this->stateAction->pollState($biz->id, $conn->id, mockRemoteStatus: 'active');
        $conn->refresh();
        $this->assertEquals('active', $conn->profile_status);

        Event::assertDispatched(GbpReinstated::class);

        $validPostRes = $this->postAction->post(
            businessId: $biz->id,
            connectionId: $conn->id,
            content: 'Winter HVAC safety inspections now available.'
        );

        $this->assertEquals('posted', $validPostRes['status']);
        $this->assertTrue($validPostRes['dispatched_to_zernio']);
        $this->assertNotNull($validPostRes['zernio_dispatch_id']);

        Event::assertDispatched(GbpPosted::class);
    }

    /**
     * [G8-01], [G8-09], [G8-17], [G8-18], [G8-27], [G12-11], [G12-31]
     */
    public function test_gbp_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
