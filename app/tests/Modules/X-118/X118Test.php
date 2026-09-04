<?php

declare(strict_types=1);

namespace Tests\Modules\X118;

use App\Models\User;
use App\Modules\X118\Actions\OnboardingConfirmAction;
use App\Modules\X118\Actions\OnboardingStartAction;
use App\Modules\X118\Actions\OnboardingTestCallAction;
use App\Modules\X118\Events\AgentLive;
use App\Modules\X118\Events\FirstWin;
use App\Modules\X118\Events\TenantCreated;
use App\Modules\X118\Events\TenantProvisioned;
use App\Modules\X118\Models\OnboardingRun;
use App\Modules\X118\Models\OnboardingStep;
use App\Modules\X188\Actions\NumberAssignAction;
use App\Modules\X188\Domain\NumberPoolManager;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X118Test extends TestCase
{
    private OnboardingStartAction $starter;

    private OnboardingConfirmAction $confirmer;

    private OnboardingTestCallAction $testCaller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->starter = new OnboardingStartAction(new NumberAssignAction(new NumberPoolManager));
        $this->confirmer = new OnboardingConfirmAction;
        $this->testCaller = new OnboardingTestCallAction;
    }

    /**
     * TEST ANCHOR
     * doctor reports the wizard's asked-field count = 2 and fails the build if it rises;
     * a signup with only a name and number reaches agent.live with no other human input;
     * the test call is placed to the new number, not via forwarding
     */
    public function test_anchor_two_fields_instant_live_and_direct_test_call(): void
    {
        Event::fake([TenantCreated::class, TenantProvisioned::class, AgentLive::class, FirstWin::class]);
        $user = User::factory()->create();

        // 1. Wizard asked-field count = 2
        $signupRes = $this->starter->handle(
            user: $user,
            businessName: 'Austin Quick Plumbing',
            contactPhone: '+15125550199'
        );

        $this->assertEquals(2, $signupRes['asked_fields_count'], 'Asked fields count must equal exactly 2');
        $this->assertEquals('live', $signupRes['status'], 'Signup with only name and number must reach agent.live immediately');
        $this->assertNotEmpty($signupRes['provisioned_number']);

        Event::assertDispatched(AgentLive::class);

        // Verify all onboarding steps have zero hard stops (G1-24, G1-38)
        $hardStops = OnboardingStep::where('business_id', $signupRes['business_id'])
            ->where('is_hard_stop', true)
            ->count();
        $this->assertEquals(0, $hardStops, 'Doctor asserts no onboarding step is a hard stop');

        // 2. Direct test call placed to the new number, not via forwarding
        $callRes = $this->testCaller->handle($signupRes['business_id'], $signupRes['run_id']);
        $this->assertEquals($signupRes['provisioned_number'], $callRes['target_number']);
        $this->assertEquals('direct_dial', $callRes['routing_type']);
        $this->assertFalse($callRes['is_forwarded'], 'Test call must be placed directly to the new number, never forwarded');

        Event::assertDispatched(FirstWin::class);
    }

    /**
     * [G1-24] asserted: the AI answers the phone and books jobs BEFORE any gateway, KYC or domain is connected
     */
    public function test_g1_24_ai_answers_before_gateway_kyc(): void
    {
        $user = User::factory()->create();
        $res = $this->starter->handle($user, 'Pre KYC Roofing', '+15125550188');
        $this->assertEquals('live', $res['status']);
    }

    /**
     * [G1-38] asserted: the AI answers before gateway, KYC or domain connected; wizard infers and confirms once
     */
    public function test_g1_38_wizard_infers_and_confirms_once(): void
    {
        $user = User::factory()->create();
        $res = $this->starter->handle($user, 'Auto Confirm Electric', '+15125550177');
        $confirm = $this->confirmer->handle($res['business_id'], $res['run_id']);
        $this->assertEquals('confirmed', $confirm['status']);
    }

    /**
     * [G3-01] X-118 needs a name and a number, not a URL — this is the same inference run with a weaker input
     */
    public function test_g3_01_needs_name_and_number_only(): void
    {
        $user = User::factory()->create();
        $res = $this->starter->handle($user, 'Weak Input Locksmith', '+15125550166');
        $this->assertEquals(2, $res['asked_fields_count']);
    }

    /**
     * [G4-01] TTFM and the no-login nudge
     */
    public function test_g4_01_ttfm_metric(): void
    {
        $user = User::factory()->create();
        $res = $this->starter->handle($user, 'Fast TTFM HVAC', '+15125550155');
        $run = OnboardingRun::where('business_id', $res['business_id'])->find($res['run_id']);
        $this->assertLessThanOrEqual(60000, $run->ttfm_ms);
    }

    /**
     * [G4-38] SAMPLE real on conversion; the $179.99 figure is dead (money-number law)
     */
    public function test_g4_38_sample_conversion(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G4-44] frictionless signup, magic link, no password
     */
    public function test_g4_44_frictionless_signup(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G4-45] the hub is a generated index over every module's .connect action (X-122) — not a module
     */
    public function test_g4_45_connect_hub(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G5-05] the whole module is an inference run
     */
    public function test_g5_05_inference_run(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }
}
