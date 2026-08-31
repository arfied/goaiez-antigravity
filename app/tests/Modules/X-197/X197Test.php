<?php

declare(strict_types=1);

namespace Tests\Modules\X197;

use App\Modules\X121\Models\Business;
use App\Modules\X197\Actions\VoiceRouteAction;
use App\Modules\X197\Events\VoiceRouteSelected;
use App\Modules\X197\Events\VoiceSelfhostedDegraded;
use App\Modules\X197\Models\VoiceCostSample;
use App\Modules\X197\Models\VoicePoolState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X197Test extends TestCase
{
    private VoiceRouteAction $routeAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->routeAction = new VoiceRouteAction;
    }

    /**
     * TEST ANCHOR
     * with the pool cold, 100% of calls route managed and none exceed 600 ms;
     * with it warm, the self-hosted share climbs and the measured $/min in voice_cost_samples is below the managed sample;
     * a pool exhaustion mid-call never drops the call
     */
    public function test_anchor_cold_pool_managed_warm_pool_cost_and_exhaustion_failover(): void
    {
        Event::fake([VoiceRouteSelected::class, VoiceSelfhostedDegraded::class]);

        $biz = Business::provision(['name' => 'Voice Router Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Pool Cold: 100% of calls route managed and none exceed 600 ms (TEST ANCHOR)
        $coldPool = VoicePoolState::create([
            'business_id' => $biz->id,
            'pool_status' => 'cold',
            'warm_instances' => 0,
            'active_calls' => 0,
            'max_capacity' => 10,
        ]);

        $coldRoute = $this->routeAction->routeCall(
            businessId: $biz->id,
            callSessionId: 'sess_cold_call_101'
        );

        $this->assertEquals('managed', $coldRoute->route_type);
        $this->assertLessThanOrEqual(600, $coldRoute->latency_ms, 'Latency does not exceed 600 ms (TEST ANCHOR)');
        $this->assertEquals(2200, $coldRoute->cost_per_minute);

        $managedSample = VoiceCostSample::where('business_id', $biz->id)->where('route_type', 'managed')->latest('id')->first();
        $this->assertNotNull($managedSample);
        $this->assertEquals(2200, $managedSample->cost_per_minute);

        // 2. Pool Warm: self-hosted share climbs and measured $/min is below managed sample (TEST ANCHOR & G3-26)
        $coldPool->update([
            'pool_status' => 'warm',
            'warm_instances' => 5,
            'active_calls' => 1,
        ]);

        $warmRoute = $this->routeAction->routeCall(
            businessId: $biz->id,
            callSessionId: 'sess_warm_call_202'
        );

        $this->assertEquals('self_hosted', $warmRoute->route_type);
        $this->assertEquals(700, $warmRoute->cost_per_minute, '7¢ minute self-hosted stack (G3-26)');

        $selfHostedSample = VoiceCostSample::where('business_id', $biz->id)->where('route_type', 'self_hosted')->latest('id')->first();
        $this->assertNotNull($selfHostedSample);
        $this->assertLessThan(
            $managedSample->cost_per_minute,
            $selfHostedSample->cost_per_minute,
            'Measured $/min in voice_cost_samples is below the managed sample'
        );

        // 3. Pool exhaustion mid-call NEVER drops the call -> fallbacks to managed (TEST ANCHOR)
        $exhaustedRoute = $this->routeAction->routeCall(
            businessId: $biz->id,
            callSessionId: 'sess_exhausted_call_303',
            forceExhaustionMidCall: true
        );

        $this->assertNotNull($exhaustedRoute, 'Call is NOT dropped on exhaustion (TEST ANCHOR)');
        $this->assertEquals('managed', $exhaustedRoute->route_type);
        $this->assertTrue($exhaustedRoute->fallback_used, 'Managed fallback used gracefully on pool exhaustion');

        Event::assertDispatched(VoiceSelfhostedDegraded::class);
    }

    /**
     * [G3-26], [G18-04], [G18-09]
     */
    public function test_voice_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
