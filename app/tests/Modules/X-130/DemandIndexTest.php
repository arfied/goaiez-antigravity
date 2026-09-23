<?php

namespace Tests\Modules\X130;

use App\Modules\X130\Actions\DemandPublishAction;
use App\Modules\X130\Actions\DemandQueryAction;
use App\Modules\X130\Events\DemandShifted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DemandIndexTest extends TestCase
{
    /**
     * [N-081], [N-082], [N-083]
     * aggregate only — a query that resolves to fewer than N tenants is REFUSED
     */
    public function test_capabilities_are_enforced_for_demand_index()
    {
        Event::fake([DemandShifted::class]);

        $publishAction = new DemandPublishAction;
        $queryAction = new DemandQueryAction;

        $regionId = 999;
        DB::table('demand_regions')->updateOrInsert(
            ['id' => $regionId],
            [
                'region_code' => 'TEST_REGION',
                'region_name' => 'Test Region',
                'trade_type' => 'plumbing',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 1. Below threshold (N=5): refused/not published
        $below = $publishAction->publishCell($regionId, '2026-10-01', 85.5, 4);
        $this->assertFalse($below->is_published);
        Event::assertNotDispatched(DemandShifted::class);

        // 2. Above threshold (N=5): published
        $above = $publishAction->publishCell($regionId, '2026-10-02', 90.0, 5);
        $this->assertTrue($above->is_published);
        Event::assertDispatched(DemandShifted::class);

        // 3. Query omits the unpublished cell
        $results = $queryAction->queryPublishedSeries($regionId);
        $this->assertCount(1, $results);
        $this->assertEquals('2026-10-02', $results->first()->period_date);
    }
}
