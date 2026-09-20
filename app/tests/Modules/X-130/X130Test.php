<?php

declare(strict_types=1);

namespace Tests\Modules\X130;

use App\Modules\X130\Actions\DemandPublishAction;
use App\Modules\X130\Actions\DemandQueryAction;
use App\Modules\X130\Events\DemandShifted;
use App\Modules\X130\Events\SeasonTurned;
use App\Modules\X130\Models\DemandRegion;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class X130Test extends TestCase
{
    /**
     * [N-070]
     */
    public function test_n_070_does_not_perform_gl_categorisation(): void
    {
        $dir = base_path('app/Modules/X-130');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $found = false;
        $match = '';
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $fileContent = file_get_contents($file->getPathname());
                if (preg_match('/(gl_category|categorisation|general_ledger|gl_account)/i', $fileContent)) {
                    $found = true;
                    $match = $file->getPathname();
                    break;
                }
            }
        }
        $this->assertFalse($found, "GL Categorisation found in X-130: $match. This belongs to X-173.");
    }

    private DemandPublishAction $publishAction;

    private DemandQueryAction $queryAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publishAction = new DemandPublishAction;
        $this->queryAction = new DemandQueryAction;
    }

    /**
     * TEST ANCHOR
     * no index table contains a tenant or person identifier — asserted by schema and by doctor;
     * a cell under the minimum source count is never published
     */
    public function test_anchor_no_tenant_person_identifiers_and_min_source_count_enforced(): void
    {
        Event::fake([DemandShifted::class, SeasonTurned::class]);

        // 1. Schema check: assert NO tenant or person identifiers on index tables (TEST ANCHOR)
        $tables = ['demand_regions', 'demand_series', 'weather_overlays'];
        $forbiddenColumns = ['business_id', 'tenant_id', 'person_id', 'account_id', 'user_id'];

        foreach ($tables as $table) {
            $columns = Schema::getColumnListing($table);
            foreach ($forbiddenColumns as $forbidden) {
                $this->assertNotContains(
                    $forbidden,
                    $columns,
                    "Table {$table} must NOT contain tenant or person identifier '{$forbidden}' (TEST ANCHOR)"
                );
            }
        }

        // 2. Set up test region
        $region = DemandRegion::create([
            'region_code' => 'TX-DFW',
            'region_name' => 'Dallas-Fort Worth Metroplex',
            'trade_type' => 'hvac',
        ]);

        // 3. Cell under minimum source count (3 < 5) -> is_published is FALSE (TEST ANCHOR)
        $underCountCell = $this->publishAction->publishCell(
            regionId: $region->id,
            periodDate: '2026-08-01',
            demandScore: 78.50,
            sourceCount: 3 // Below min source threshold of 5
        );

        $this->assertFalse($underCountCell->is_published, 'Cell under minimum source count is NEVER published (TEST ANCHOR)');
        Event::assertNotDispatched(DemandShifted::class);

        // 4. Query action confirms unpublished cell is omitted
        $publishedList = $this->queryAction->queryPublishedSeries($region->id);
        $this->assertCount(0, $publishedList);

        // 5. Cell at or above minimum source count (8 >= 5) -> is_published is TRUE (TEST ANCHOR)
        $validCell = $this->publishAction->publishCell(
            regionId: $region->id,
            periodDate: '2026-08-01',
            demandScore: 88.00,
            sourceCount: 8,
            isSeasonalTurn: true
        );

        $this->assertTrue($validCell->is_published, 'Cell with sufficient source count is published');
        Event::assertDispatched(DemandShifted::class);
        Event::assertDispatched(SeasonTurned::class);

        $publishedListAfter = $this->queryAction->queryPublishedSeries($region->id);
        $this->assertCount(1, $publishedListAfter);
    }

    /**
     * [N-062]
     * [N-063] ⛔ REFUSED: `php artisan why N-063` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across modules —
     *   it names other modules entirely. Nothing to assert. (R245, REV-80/REV-81)
     * [N-079] ⛔ REFUSED: `php artisan why N-079` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across modules —
     *   it names other modules entirely. Nothing to assert. (R245, REV-80/REV-81)
     * [N-081]
     * [N-082]
     * [N-083]
     * [N-085] ⛔ REFUSED: `php artisan why N-085` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across modules —
     *   it names other modules entirely. Nothing to assert. (R245, REV-80/REV-81)
     */
    public function test_demand_capabilities(): void
    {
        $engine = new \App\Modules\X130\Domain\DemandEngine();

        // [N-081, N-082, N-083] aggregate only — refuses below N tenants (N=5)
        $refused = $engine->query(4);
        $this->assertEquals('refused', $refused['status']);
        $this->assertEquals('below_n', $refused['reason']);

        $allowed = $engine->query(5);
        $this->assertEquals('aggregate_only', $allowed['status']);
    }
}
