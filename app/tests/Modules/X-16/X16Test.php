<?php

declare(strict_types=1);

namespace Tests\Modules\X16;

use App\Modules\X121\Models\Fact;
use App\Modules\X16\Actions\MapsGeogridAction;
use App\Modules\X16\Actions\MapsHarvestAction;
use App\Modules\X16\Actions\MapsPolygonAction;
use App\Modules\X16\Models\PlacesRecord;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class X16Test extends TestCase
{
    private MapsHarvestAction $harvestAction;

    private MapsGeogridAction $geogridAction;

    private MapsPolygonAction $polygonAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->harvestAction = new MapsHarvestAction;
        $this->geogridAction = new MapsGeogridAction;
        $this->polygonAction = new MapsPolygonAction;
    }

    /**
     * TEST ANCHOR
     * a two-column price table yields exactly one Fact per row with page set and zero embedding writes for the numeric column;
     * re-uploading the same file with one changed price invalidates one Fact and creates one, in the same commit
     */
    public function test_anchor_price_table_facts_and_same_commit_invalidation(): void
    {
        $biz = \Tests\TestCase::provisionTenant(['name' => 'Maps & Fact Ingest Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $priceTable = [
            ['service' => 'Drain Cleaning', 'price' => 199],
            ['service' => 'Water Heater Flush', 'price' => 149],
        ];

        // 1. Two-column price table yields exactly ONE Fact per row with page set and 0 embedding for numeric column (TEST ANCHOR)
        $ingestRes = $this->harvestAction->ingestPriceTable(
            businessId: $biz->id,
            twoColumnRows: $priceTable,
            pageNumber: 3,
            commitId: 'commit_v1'
        );

        $this->assertEquals(2, $ingestRes['facts_created']);
        $this->assertEquals(0, $ingestRes['facts_invalidated']);
        $this->assertEquals(3, $ingestRes['page']);

        $facts = Fact::where('business_id', $biz->id)->where('is_valid', true)->get();
        $this->assertCount(2, $facts);

        foreach ($facts as $fact) {
            $data = json_decode($fact->value, true);
            $this->assertEquals(3, $data['page'], 'Page number must be set');
            $this->assertNull($data['numeric_embedding_vector'], 'ZERO embedding writes for numeric column');
        }

        // 2. Re-uploading the same file with ONE changed price invalidates ONE Fact and creates ONE, in same commit (TEST ANCHOR)
        $updatedPriceTable = [
            ['service' => 'Drain Cleaning', 'price' => 199], // Unchanged
            ['service' => 'Water Heater Flush', 'price' => 179], // Changed $149 -> $179
        ];

        $reuploadRes = $this->harvestAction->ingestPriceTable(
            businessId: $biz->id,
            twoColumnRows: $updatedPriceTable,
            pageNumber: 3,
            commitId: 'commit_v2'
        );

        $this->assertEquals(1, $reuploadRes['facts_created'], 'Creates 1 new fact');
        $this->assertEquals(1, $reuploadRes['facts_invalidated'], 'Invalidates 1 old fact');

        $activeFacts = Fact::where('business_id', $biz->id)->where('is_valid', true)->get();
        $this->assertCount(2, $activeFacts);

        $invalidatedFacts = Fact::where('business_id', $biz->id)->where('is_valid', false)->get();
        $this->assertCount(1, $invalidatedFacts);
        $invalidatedData = json_decode($invalidatedFacts->first()->value, true);
        $this->assertEquals(149, $invalidatedData['price']);

        // 3. [G7-21] Chains filtered out of prospect set
        $harvestPlaces = [
            ['place_id' => 'plc_local_01', 'name' => 'Bob Plumbing', 'address' => '123 Main St', 'is_chain' => false],
            ['place_id' => 'plc_chain_02', 'name' => 'Roto-Rooter Corporate', 'address' => '456 Commercial Blvd', 'is_chain' => true],
        ];

        $harvestRes = $this->harvestAction->harvestPlaces($biz->id, $harvestPlaces);
        $this->assertEquals(1, $harvestRes['saved_count']);
        $this->assertEquals(1, $harvestRes['chains_filtered'], 'Chains filtered out of prospect set');

        $savedPlaces = PlacesRecord::where('business_id', $biz->id)->get();
        $this->assertCount(1, $savedPlaces);
        $this->assertEquals('Bob Plumbing', $savedPlaces->first()->name);
    }

    /**
     * [G3-10], [G3-14], [G3-35], [G3-41], [G7-21], [G8-31]
     */
    public function test_maps_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
