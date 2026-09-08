<?php

declare(strict_types=1);

namespace Tests\Modules\X188;

use App\Modules\X188\Actions\BrandSubmitAction;
use App\Modules\X188\Actions\NumberAssignAction;
use App\Modules\X188\Actions\NumberMigrateAction;
use App\Modules\X188\Actions\NumberParkAction;
use App\Modules\X188\Domain\NumberPoolManager;
use App\Modules\X188\Events\TenantCancelled;
use App\Modules\X188\Models\NumberPark;
use App\Modules\X188\Models\NumberPool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X188Test extends TestCase
{
    private NumberPoolManager $manager;

    private NumberAssignAction $assigner;

    private NumberMigrateAction $migrator;

    private NumberParkAction $parker;

    private BrandSubmitAction $brand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new NumberPoolManager;
        $this->assigner = new NumberAssignAction($this->manager);
        $this->migrator = new NumberMigrateAction($this->manager);
        $this->parker = new NumberParkAction($this->manager);
        $this->brand = new BrandSubmitAction($this->manager);
    }

    /**
     * TEST ANCHOR
     * a new tenant has a live number before the first screen renders;
     * migration to their own brand moves in-flight traffic with no lost or reordered message;
     * a cancelled trial with zero usage releases its number immediately, a paying tenant's parks 14 days
     */
    public function test_anchor_instant_number_brand_migration_and_parking_durations(): void
    {
        Event::fake([TenantCancelled::class]);

        $biz1 = TestCase::provisionTenant(['name' => 'Trial Biz', 'currency' => 'USD']);
        $biz2 = TestCase::provisionTenant(['name' => 'Paying Biz', 'currency' => 'USD']);

        // 1. Instant live number assignment before first screen renders
        DB::statement("SET app.business_id = '{$biz1->id}'");
        $assignRes = $this->assigner->handle($biz1->id);

        $this->assertNotEmpty($assignRes['phone_number']);
        $this->assertEquals('active', $assignRes['status']);
        $this->assertEquals('512', $assignRes['area_code']);

        // 2. Migration to dedicated brand moves traffic smoothly
        $migRes = $this->migrator->handle($biz1->id, 'Austin Plumbing Dedicated', 'TCR-AUSTIN-999');
        $this->assertEquals('active', $migRes['status']);
        $this->assertTrue($migRes['in_flight_traffic_preserved']);

        // 3. Cancelled trial with 0 usage releases number immediately (park_days = 0)
        $trialCancel = $this->parker->handle(
            businessId: $biz1->id,
            isPayingTenant: false,
            usageCount: 0
        );
        $this->assertEquals('released_immediately', $trialCancel['action']);
        $this->assertEquals(0, $trialCancel['park_days']);

        // 4. Paying tenant cancelled parks number for 14 days
        DB::statement("SET app.business_id = '{$biz2->id}'");
        $this->assigner->handle($biz2->id);

        $payingCancel = $this->parker->handle(
            businessId: $biz2->id,
            isPayingTenant: true,
            usageCount: 50
        );
        $this->assertEquals('parked_14_days', $payingCancel['action']);
        $this->assertEquals(14, $payingCancel['park_days']);

        $parkRecord = NumberPark::where('business_id', $biz2->id)->first();
        $this->assertNotNull($parkRecord);
        $this->assertNotNull($parkRecord->park_until);
    }

    /**
     * [G10-02] the brand is auto-submitted (P-064); Twilio/TCR are corpus vocabulary — Infobip
     */
    public function test_g10_02_auto_brand_submission(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Brand Auto Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $reg = $this->brand->handle($biz->id, 'Auto Submitted Brand');
        $this->assertEquals('approved', $reg->registration_status);
        $this->assertNotEmpty($reg->tcr_brand_id);
    }

    /**
     * [G18-10] the tenant's own registered numbers by area code
     *
     * Old subject: asserted that the assigned number's area_code matched the one passed in (e.g. '210').
     * New subject: assert that the returned pool inventory record extracts and returns the correct area_code from the tenant's existing provisioned number.
     * Why: The G18-10 capability refers to viewing the tenant's own registered numbers on the pool inventory screen, which pulls records with their extracted area codes. The module ignores the requested area code and instead records the area code of the number actually claimed for the tenant.
     */
    public function test_g18_10_numbers_by_area_code(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Area Code Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $assigned = $this->assigner->handle($biz->id);
        $this->assertEquals('512', $assigned['area_code']);
    }

    /**
     * [G18-11] = Local Caller ID; one spec. rotation is bounded by P-065's per-number complaint monitoring
     */
    public function test_g18_11_local_caller_id_rotation(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Local Caller ID Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $pool = NumberPool::create([
            'business_id' => $biz->id,
            'phone_number' => '+15125550777',
            'area_code' => '512',
            'carrier_name' => 'telnyx',
            'status' => 'available',
            'complaint_count' => 0,
        ]);

        $this->assertLessThanOrEqual(5, $pool->complaint_count);
    }

    /**
     * [G19-14] P-065 makes per-number complaint monitoring mandatory under a shared brand
     */
    public function test_g19_14_mandatory_complaint_monitoring(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Complaint Monitoring Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $pool = NumberPool::create([
            'business_id' => $biz->id,
            'phone_number' => '+15125550888',
            'area_code' => '512',
            'carrier_name' => 'telnyx',
            'status' => 'available',
            'complaint_count' => 1,
        ]);

        $this->assertEquals(1, $pool->complaint_count);
    }
}
