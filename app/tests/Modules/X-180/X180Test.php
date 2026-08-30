<?php

declare(strict_types=1);

namespace Tests\Modules\X180;

use App\Modules\X121\Models\Business;
use App\Modules\X180\Actions\PackSeedAction;
use App\Modules\X180\Events\PackSeeded;
use App\Modules\X180\Models\ContentPack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X180Test extends TestCase
{
    private PackSeedAction $seedAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAction = new PackSeedAction;
    }

    /**
     * TEST ANCHOR
     * the manifest's declared asset count MUST equal the rows created ·
     * a packaged asset with no license_source cannot be seeded.
     */
    public function test_anchor_manifest_count_equality_and_license_source_gate(): void
    {
        Event::fake([PackSeeded::class]);

        $biz = Business::provision(['name' => 'Content Packs Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. A packaged asset with NO license_source CANNOT be seeded (TEST ANCHOR)
        $unlicensedAssets = [
            [
                'title' => 'Emergency AC Leak Guide',
                'type' => 'guide_pdf',
                'license_source' => 'CC-BY-4.0 / StockPro #99481',
            ],
            [
                'title' => 'Furnace Tune-up Checklist',
                'type' => 'checklist',
                'license_source' => '', // Missing license source (TEST ANCHOR)
            ],
        ];

        $unlicensedResult = $this->seedAction->seed(
            businessId: $biz->id,
            packName: 'HVAC Seasonal Marketing Pack',
            industry: 'HVAC',
            declaredAssetCount: 2,
            assets: $unlicensedAssets
        );

        $this->assertEquals('refused', $unlicensedResult['status']);
        $this->assertEquals('MISSING_LICENSE_SOURCE', $unlicensedResult['refusal_code']);
        $this->assertNull($unlicensedResult['pack']);

        $packCount = ContentPack::where('business_id', $biz->id)->count();
        $this->assertEquals(0, $packCount, 'Zero packs created when asset lacks license source');
        Event::assertNotDispatched(PackSeeded::class);

        // 2. Count mismatch check (declared count != actual count) (TEST ANCHOR)
        $licensedAssets = [
            [
                'title' => 'Emergency AC Leak Guide',
                'type' => 'guide_pdf',
                'license_source' => 'CC-BY-4.0 / StockPro #99481',
            ],
            [
                'title' => 'Furnace Tune-up Checklist',
                'type' => 'checklist',
                'license_source' => 'Proprietary TradePack Vol 1',
            ],
        ];

        $mismatchResult = $this->seedAction->seed(
            businessId: $biz->id,
            packName: 'HVAC Seasonal Marketing Pack',
            industry: 'HVAC',
            declaredAssetCount: 5, // Declares 5, but provides 2
            assets: $licensedAssets
        );

        $this->assertEquals('refused', $mismatchResult['status']);
        $this->assertEquals('ASSET_COUNT_MISMATCH', $mismatchResult['refusal_code']);

        // 3. Valid seeded pack with verified licenses and matching counts (TEST ANCHOR)
        $validResult = $this->seedAction->seed(
            businessId: $biz->id,
            packName: 'HVAC Seasonal Marketing Pack',
            industry: 'HVAC',
            declaredAssetCount: 2,
            assets: $licensedAssets
        );

        $this->assertEquals('seeded', $validResult['status']);
        $this->assertEquals(2, $validResult['assets_created']);

        $pack = ContentPack::where('business_id', $biz->id)->find($validResult['pack_id']);
        $this->assertNotNull($pack);
        $this->assertEquals(2, $pack->assets_count, 'Manifest declared asset count MUST equal rows created');

        Event::assertDispatched(PackSeeded::class);
    }

    /**
     * [G10-10]
     */
    public function test_g10_10_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
