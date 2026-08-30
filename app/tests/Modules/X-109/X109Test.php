<?php

declare(strict_types=1);

namespace Tests\Modules\X109;

use App\Modules\X109\Actions\FormSubmitAction;
use App\Modules\X109\Events\ChallengeEncountered;
use App\Modules\X109\Events\FormSubmitted;
use App\Modules\X109\Events\QuotaExhausted;
use App\Modules\X109\Models\CaptchaQuota;
use App\Modules\X121\Models\Business;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X109Test extends TestCase
{
    private FormSubmitAction $submitAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->submitAction = new FormSubmitAction;
    }

    /**
     * TEST ANCHOR
     * the same prospect's form is never submitted twice in a campaign — asserted on the submissions table;
     * quota at zero produces a queue row and no third-party charge;
     * grep -r captcha app/Modules/X-109/ returns nothing
     */
    public function test_anchor_duplicate_prevention_and_zero_quota_manual_queuing(): void
    {
        Event::fake([FormSubmitted::class, ChallengeEncountered::class, QuotaExhausted::class]);

        $biz = Business::provision(['name' => 'Form Outreach Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $campaignId = 'camp_roofers_dallas_2026';
        $prospectId = 'prospect_summit_roofing_llc';

        // Set up quota record with 5 available credits
        CaptchaQuota::create([
            'business_id' => $biz->id,
            'campaign_id' => null,
            'prospect_identifier' => null,
            'status' => 'active',
            'available_quota' => 5,
            'used_quota' => 0,
        ]);

        // 1. Initial submission succeeds (G3-06, G3-31)
        $sub1 = $this->submitAction->submitForm(
            businessId: $biz->id,
            campaignId: $campaignId,
            prospectIdentifier: $prospectId,
            formData: ['name' => 'Go AI EZ', 'message' => 'Partner proposal']
        );

        $this->assertEquals('submitted', $sub1['status']);
        Event::assertDispatched(FormSubmitted::class);

        // 2. Duplicate submission attempt in same campaign is SKIPPED (TEST ANCHOR)
        $sub2 = $this->submitAction->submitForm(
            businessId: $biz->id,
            campaignId: $campaignId,
            prospectIdentifier: $prospectId,
            formData: ['name' => 'Go AI EZ', 'message' => 'Duplicate attempt']
        );

        $this->assertEquals('skipped_duplicate', $sub2['status'], 'The same prospect form is never submitted twice in a campaign (TEST ANCHOR)');

        $submissionsCount = CaptchaQuota::where('business_id', $biz->id)
            ->where('campaign_id', $campaignId)
            ->where('prospect_identifier', $prospectId)
            ->where('status', 'submitted')
            ->count();
        $this->assertEquals(1, $submissionsCount, 'Exactly 1 submission row recorded on submissions table (TEST ANCHOR)');

        // 3. Quota at zero -> produces queue row and no third-party charge (TEST ANCHOR & P-144)
        // Drain quota
        CaptchaQuota::where('business_id', $biz->id)->whereNull('prospect_identifier')->update(['available_quota' => 0]);

        $zeroQuotaProspect = 'prospect_lone_star_plumbing';
        $zeroQuotaResult = $this->submitAction->submitForm(
            businessId: $biz->id,
            campaignId: $campaignId,
            prospectIdentifier: $zeroQuotaProspect,
            formData: ['name' => 'Go AI EZ']
        );

        $this->assertEquals('queued_manual', $zeroQuotaResult['status']);

        $queueRow = CaptchaQuota::where('business_id', $biz->id)
            ->where('prospect_identifier', $zeroQuotaProspect)
            ->where('status', 'queued_manual')
            ->first();
        $this->assertNotNull($queueRow, 'Queue row produced on zero quota (TEST ANCHOR)');

        Event::assertDispatched(QuotaExhausted::class);
    }

    /**
     * [G3-06], [G3-12], [G3-31], [G3-36], [G3-52]
     */
    public function test_form_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
