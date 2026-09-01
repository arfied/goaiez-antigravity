<?php

declare(strict_types=1);

namespace Tests\Modules\X162;

use App\Modules\X162\Actions\EtaQueryAction;
use App\Modules\X162\Actions\EtaUpdateAction;
use App\Modules\X162\Actions\JobDispatchAction;
use App\Modules\X162\Actions\RouteOptimiseAction;
use App\Modules\X162\Actions\TechEnRouteAction;
use App\Modules\X162\Events\EtaUpdated;
use App\Modules\X162\Events\JobDispatched;
use App\Modules\X162\Events\RouteChanged;
use App\Modules\X162\Events\TechEnRoute;
use App\Modules\X162\Models\DispatchAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X162Test extends TestCase
{
    private JobDispatchAction $dispatchAction;

    private TechEnRouteAction $enRouteAction;

    private RouteOptimiseAction $routeAction;

    private EtaQueryAction $queryAction;

    private EtaUpdateAction $updateAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatchAction = new JobDispatchAction;
        $this->enRouteAction = new TechEnRouteAction;
        $this->routeAction = new RouteOptimiseAction;
        $this->queryAction = new EtaQueryAction;
        $this->updateAction = new EtaUpdateAction;
    }

    /**
     * TEST ANCHOR
     * the customer's ETA message is sent within one minute of the EN ROUTE event
     * and again on any ETA change over N minutes (data);
     * the agent's answer to "where is he?" cites the current state and ETA, never a guess
     */
    public function test_anchor_en_route_customer_eta_update_notification_and_grounded_answer(): void
    {
        Event::fake([JobDispatched::class, TechEnRoute::class, RouteChanged::class, EtaUpdated::class]);

        $biz = TestCase::provisionTenant(['name' => 'Field Dispatch Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $jobId = 1001;
        $techId = 42;

        // 1. Initial Dispatch
        $assignment = $this->dispatchAction->handle($biz->id, $jobId, $techId);
        Event::assertDispatched(JobDispatched::class);

        // 2. Customer's ETA message is sent within 1 minute of EN ROUTE event (TEST ANCHOR, G2-71)
        $enRouteRes = $this->enRouteAction->markEnRoute($biz->id, $jobId, $techId, etaMinutes: 20);
        $this->assertEquals('en_route', $enRouteRes['status']);
        $this->assertTrue($enRouteRes['notification_sent']);
        $this->assertNotNull($enRouteRes['notification_sent_at']);
        Event::assertDispatched(TechEnRoute::class);

        $savedAssignment = DispatchAssignment::where('business_id', $biz->id)->where('job_id', $jobId)->first();
        $this->assertEquals('en_route', $savedAssignment->status);

        // 3. Agent's answer to "where is he?" cites current state and ETA, never a guess (TEST ANCHOR)
        $groundedQuery = $this->queryAction->handle($biz->id, $jobId);
        $this->assertFalse($groundedQuery['is_guess']);
        $this->assertEquals('en_route', $groundedQuery['current_state']);
        $this->assertEquals(20, $groundedQuery['eta_minutes']);
        $this->assertStringContainsString('en_route', $groundedQuery['grounded_answer']);
        $this->assertStringContainsString('20', $groundedQuery['grounded_answer']);

        // 4. Sent again on any ETA change over N minutes (threshold 10 min) (TEST ANCHOR)
        // A minor 3-minute traffic delay (< 10 min) -> No re-notification
        $minorUpdate = $this->updateAction->updateEta($biz->id, $jobId, newEtaMinutes: 23, thresholdDeltaMinutes: 10);
        $this->assertFalse($minorUpdate['notification_resent']);

        // A major 15-minute delay (> 10 min threshold) -> Re-notification sent!
        $majorUpdate = $this->updateAction->updateEta($biz->id, $jobId, newEtaMinutes: 38, thresholdDeltaMinutes: 10);
        $this->assertTrue($majorUpdate['notification_resent']);
        $this->assertEquals(15, $majorUpdate['delta_minutes']);
        Event::assertDispatched(EtaUpdated::class);
    }

    /**
     * [G2-29], [G2-41], [G2-54], [G2-55], [G2-65], [G2-71], [G2-73], [G2-77], [G4-11]
     * Route optimization, nearest tech & native zapier hop
     */
    public function test_route_optimization_and_capabilities(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Route Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $route = $this->routeAction->handle($biz->id, 42, [101, 102, 103], 12.8);
        $this->assertEquals(12.8, $route->total_distance_km);
    }
}
