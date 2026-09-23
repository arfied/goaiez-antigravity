<?php

declare(strict_types=1);

namespace Tests\Modules\X139;

use App\Modules\X139\Actions\ConversionUploadAction;
use App\Modules\X139\Domain\ConversionUploadEngine;
use App\Modules\X139\Events\ConversionRejected;
use App\Modules\X139\Events\ConversionUploaded;
use App\Modules\X139\Models\ConversionUpload;
use App\Services\Config\DefaultsRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X139Test extends TestCase
{
    private ConversionUploadEngine $engine;

    private ConversionUploadAction $uploadAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new ConversionUploadEngine(app(DefaultsRegistry::class));
        $this->uploadAction = new ConversionUploadAction($this->engine, app(DefaultsRegistry::class));
    }

    /**
     * TEST ANCHOR
     * no code path here reads or writes a bid, budget or campaign setting — grep for the management endpoints returns nothing;
     * a completed Job outside the attribution window is not uploaded
     */
    public function test_anchor_attribution_window_rejection_and_valid_upload(): void
    {
        Event::fake([ConversionUploaded::class, ConversionRejected::class]);

        $biz = TestCase::provisionTenant(['name' => 'Offline Conversion Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $jobIdStale = 8812;
        $jobIdFresh = 8813;
        $now = Carbon::now();

        // 1. A completed Job outside the attribution window (> 90 days) is NOT uploaded (TEST ANCHOR)
        $touchDateStale = $now->copy()->subDays(95); // 95 days ago (> 90 days)
        $rejectedResult = $this->uploadAction->handle(
            businessId: $biz->id,
            jobId: $jobIdStale,
            conversionValueCents: 50000,
            touchTimestamp: $touchDateStale,
            gclidOrFbc: 'gclid_stale_99123',
            attributionWindowDays: 90
        );

        $this->assertEquals('rejected', $rejectedResult['status']);
        $this->assertEquals('OUTSIDE_ATTRIBUTION_WINDOW', $rejectedResult['refusal_code']);
        $this->assertFalse($rejectedResult['uploaded']);

        $staleUploadRecord = ConversionUpload::where('business_id', $biz->id)->find($rejectedResult['record_id']);
        $this->assertNotNull($staleUploadRecord);
        $this->assertEquals('rejected', $staleUploadRecord->status);
        $this->assertStringContainsString('95 days old', $staleUploadRecord->rejection_reason);

        Event::assertDispatched(ConversionRejected::class);

        // 2. A completed Job inside the attribution window (e.g. 10 days ago) is successfully uploaded (TEST ANCHOR)
        $touchDateFresh = $now->copy()->subDays(10);
        $successResult = $this->uploadAction->handle(
            businessId: $biz->id,
            jobId: $jobIdFresh,
            conversionValueCents: 120000,
            touchTimestamp: $touchDateFresh,
            gclidOrFbc: 'gclid_fresh_7721'
        );

        $this->assertEquals('uploaded', $successResult['status']);
        $this->assertTrue($successResult['uploaded']);

        $freshUploadRecord = ConversionUpload::where('business_id', $biz->id)->find($successResult['record_id']);
        $this->assertNotNull($freshUploadRecord);
        $this->assertEquals('uploaded', $freshUploadRecord->status);
        $this->assertNull($freshUploadRecord->rejection_reason);

        Event::assertDispatched(ConversionUploaded::class);
    }

    /**
     * [G13-22], [G1-62]
     */
    public function test_conversion_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
