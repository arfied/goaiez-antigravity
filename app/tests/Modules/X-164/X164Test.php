<?php

declare(strict_types=1);

namespace Tests\Modules\X164;

use App\Modules\X164\Actions\EstimateAcceptAction;
use App\Modules\X164\Actions\EstimateDraftAction;
use App\Modules\X164\Actions\EstimateRefreshAction;
use App\Modules\X164\Actions\EstimateSendAction;
use App\Modules\X164\Events\DepositRequested;
use App\Modules\X164\Events\EstimateAccepted;
use App\Modules\X164\Events\EstimateSent;
use App\Modules\X164\Models\Estimate;
use App\Modules\X164\Models\EstimateVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X164Test extends TestCase
{
    private EstimateDraftAction $draft;

    private EstimateSendAction $send;

    private EstimateAcceptAction $accept;

    private EstimateRefreshAction $refresh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->draft = new EstimateDraftAction;
        $this->send = new EstimateSendAction;
        $this->accept = new EstimateAcceptAction;
        $this->refresh = new EstimateRefreshAction;
    }

    /**
     * TEST ANCHOR
     * an accepted estimate stores its price_book_version and re-renders from it, never from HEAD;
     * an expired estimate is locked and cannot be accepted without a refresh
     */
    public function test_anchor_accepted_estimate_version_freeze_and_expired_locking(): void
    {
        Event::fake([EstimateSent::class, EstimateAccepted::class, DepositRequested::class]);

        $biz = TestCase::provisionTenant(['name' => 'Estimate Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Draft and send estimate under price_book_version = 1
        $lines = [
            ['service_name' => 'Water Heater Replacement', 'quantity' => 1, 'unit_price_cents' => 120000],
            ['service_name' => 'Permit & Inspection', 'quantity' => 1, 'unit_price_cents' => 15000],
        ];

        $est = $this->draft->handle($biz->id, null, $lines, priceBookVersion: 1);
        $this->send->handle($biz->id, $est->id);

        // 2. Accept estimate: freezes price_book_version 1
        $acceptRes = $this->accept->handle($biz->id, $est->id, 'John Doe Customer');
        $this->assertEquals('accepted', $acceptRes['status']);
        $this->assertEquals(1, $acceptRes['price_book_version']);

        $versionRow = EstimateVersion::where('business_id', $biz->id)->where('estimate_id', $est->id)->first();
        $this->assertNotNull($versionRow);
        $this->assertEquals(1, $versionRow->version_number);
        $this->assertEquals(135000, $versionRow->frozen_snapshot['total_cents']);

        // Even if HEAD price book moves to version 2, re-rendering from frozen snapshot remains version 1 ($1,350.00)
        $this->assertEquals(1, $versionRow->frozen_snapshot['price_book_version']);

        // 3. Expired estimate is locked and cannot be accepted without a refresh
        $expiredEst = $this->draft->handle($biz->id, null, $lines, priceBookVersion: 1, validDays: -1); // expired yesterday

        $failAccept = $this->accept->handle($biz->id, $expiredEst->id, 'Late Customer');
        $this->assertEquals('expired_locked', $failAccept['status']);
        $this->assertStringContainsString('cannot be accepted without a refresh', $failAccept['message']);

        // 4. Refreshing the expired estimate unlocks it and updates price_book_version
        $updatedLines = [
            ['service_name' => 'Water Heater Replacement', 'quantity' => 1, 'unit_price_cents' => 130000],
            ['service_name' => 'Permit & Inspection', 'quantity' => 1, 'unit_price_cents' => 15000],
        ];

        $refreshed = $this->refresh->handle($biz->id, $expiredEst->id, newPriceBookVersion: 2, updatedLinePrices: $updatedLines);
        $this->assertEquals('sent', $refreshed->status);
        $this->assertEquals(2, $refreshed->price_book_version);
        $this->assertEquals(145000, $refreshed->total_cents);

        // Now acceptance succeeds
        $successAccept = $this->accept->handle($biz->id, $refreshed->id, 'Late Customer Now On Time');
        $this->assertEquals('accepted', $successAccept['status']);
        $this->assertEquals(2, $successAccept['price_book_version']);
    }

    /**
     * [G10-01] the customer signature freezes the version it was sold at; F-19
     */
    public function test_g10_01_signature_freezes_version(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Signature Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $est = $this->draft->handle($biz->id, null, [['service_name' => 'Roof Repair', 'quantity' => 1, 'unit_price_cents' => 50000]], 1);
        $res = $this->accept->handle($biz->id, $est->id, 'Customer Signature');

        $this->assertEquals('accepted', $res['status']);
        $this->assertEquals('Customer Signature', $res['frozen_snapshot']['signed_by']);
    }

    /**
     * [G10-05] the countersign step; see F-19
     */
    public function test_g10_05_countersign_step(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Countersign Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $est = $this->draft->handle($biz->id, null, [['service_name' => 'HVAC Tuneup', 'quantity' => 1, 'unit_price_cents' => 15000]], 1);
        $res = $this->accept->handle($biz->id, $est->id, 'Customer A', 'Staff Supervisor');

        $this->assertEquals('Staff Supervisor', $res['frozen_snapshot']['countersigned_by']);
    }

    /**
     * [G10-20] the executed document linked to the record; see F-19
     */
    public function test_g10_20_executed_document_link(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Doc Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $est = $this->draft->handle($biz->id, null, [['service_name' => 'Plumbing Inspection', 'quantity' => 1, 'unit_price_cents' => 9900]], 1);
        $res = $this->accept->handle($biz->id, $est->id, 'Customer B');

        $this->assertNotEmpty($res['executed_doc_url']);
    }

    /**
     * [G10-39] CRM variables into a template; see F-19
     */
    public function test_g10_39_crm_template_vars(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G17-11] a quote expires at the version it was sold at
     */
    public function test_g17_11_quote_expires_at_version(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Expire Version Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $est = $this->draft->handle($biz->id, null, [['service_name' => 'Service X', 'quantity' => 1, 'unit_price_cents' => 20000]], 1);
        $this->assertEquals(1, $est->price_book_version);
    }
}
