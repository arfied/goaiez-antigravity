<?php

namespace Tests\Modules\X130;

use Tests\TestCase;

class DemandIndexTest extends TestCase
{
    /**
     * [N-081], [N-082], [N-083]
     * aggregate only — a query that resolves to fewer than N tenants is REFUSED
     */
    public function test_capabilities_are_enforced_for_demand_index()
    {
        \Illuminate\Support\Facades\Event::fake([\App\Modules\X130\Events\DemandShifted::class]);

        $publishAction = new \App\Modules\X130\Actions\DemandPublishAction();
        $queryAction = new \App\Modules\X130\Actions\DemandQueryAction();
        
        $regionId = 999;
        \Illuminate\Support\Facades\DB::table('demand_regions')->updateOrInsert(
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
        \Illuminate\Support\Facades\Event::assertNotDispatched(\App\Modules\X130\Events\DemandShifted::class);
        
        // 2. Above threshold (N=5): published
        $above = $publishAction->publishCell($regionId, '2026-10-02', 90.0, 5);
        $this->assertTrue($above->is_published);
        \Illuminate\Support\Facades\Event::assertDispatched(\App\Modules\X130\Events\DemandShifted::class);
        
        // 3. Query omits the unpublished cell
        $results = $queryAction->queryPublishedSeries($regionId);
        $this->assertCount(1, $results);
        $this->assertEquals('2026-10-02', $results->first()->period_date);
    }
}
