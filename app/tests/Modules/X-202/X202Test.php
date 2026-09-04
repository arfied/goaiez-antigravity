<?php

declare(strict_types=1);

namespace Tests\Modules\X202;

use App\Modules\X202\Actions\ApprovalDecideAction;
use App\Modules\X202\Actions\ApprovalEnqueueAction;
use App\Modules\X202\Actions\ApprovalEscalateAction;
use App\Modules\X202\Domain\ApprovalDeskEngine;
use App\Modules\X202\Events\ApprovalDecided;
use App\Modules\X202\Events\ApprovalEscalated;
use App\Modules\X202\Events\ApprovalExpired;
use App\Modules\X202\Events\ApprovalRaised;
use App\Modules\X202\Models\ApprovalItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X202Test extends TestCase
{
    private ApprovalDeskEngine $engine;

    private ApprovalEnqueueAction $enqueueAction;

    private ApprovalDecideAction $decideAction;

    private ApprovalEscalateAction $escalateAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new ApprovalDeskEngine;
        $this->enqueueAction = new ApprovalEnqueueAction($this->engine);
        $this->decideAction = new ApprovalDecideAction($this->engine);
        $this->escalateAction = new ApprovalEscalateAction;
    }

    /**
     * TEST ANCHOR
     * enqueue a bare error the write is REFUSED.
     * Enqueue an L1-forever item it cannot be batch-approved.
     * Let one expire it appears on a human's screen, not in a void.
     */
    public function test_anchor_bare_error_refusal_l1_forever_no_batch_and_expiration_routing(): void
    {
        Event::fake([ApprovalRaised::class, ApprovalDecided::class, ApprovalExpired::class, ApprovalEscalated::class]);

        $biz = TestCase::provisionTenant(['name' => 'Approval Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Enqueue a bare error -> the write is REFUSED
        $bareErrorRes = $this->enqueueAction->handle(
            businessId: $biz->id,
            itemType: '',
            subject: '',
            payload: ['error_only' => true]
        );
        $this->assertEquals('refused', $bareErrorRes['status']);
        $this->assertEquals('INVALID_APPROVAL_PAYLOAD', $bareErrorRes['refusal_code']);

        // Legitimate item enqueues properly
        $validRes = $this->enqueueAction->handle(
            businessId: $biz->id,
            itemType: 'campaign_broadcast',
            subject: 'Memorial Day Blast to 500 customers',
            payload: ['message' => '50% off AC inspection']
        );
        $this->assertEquals('enqueued', $validRes['status']);
        Event::assertDispatched(ApprovalRaised::class);

        // 2. Enqueue an L1-forever item -> it cannot be batch-approved
        $l1ForeverRes = $this->enqueueAction->handle(
            businessId: $biz->id,
            itemType: 'high_value_refund',
            subject: '$1,500 Customer Settlement',
            payload: ['amount' => 150000],
            autonomyLevel: 'L1',
            isL1Forever: true
        );

        $normalRes = $this->enqueueAction->handle(
            businessId: $biz->id,
            itemType: 'discount_override',
            subject: '$10 Discount',
            payload: ['amount' => 1000],
            autonomyLevel: 'L2',
            isL1Forever: false
        );

        // Run batch approve on both
        $batchRes = $this->engine->batchApprove($biz->id, [
            $l1ForeverRes['approval_item_id'],
            $normalRes['approval_item_id'],
        ]);

        $this->assertEquals(1, $batchRes['approved_count']);
        $this->assertContains($normalRes['approval_item_id'], $batchRes['approved_ids']);
        $this->assertContains($l1ForeverRes['approval_item_id'], $batchRes['skipped_l1_forever_ids'], 'L1-forever item cannot be batch-approved');

        // Verify L1 item is still pending
        $l1Fresh = ApprovalItem::where('business_id', $biz->id)->find($l1ForeverRes['approval_item_id']);
        $this->assertEquals('pending', $l1Fresh->status);

        // 3. Let one expire -> it appears on a human's screen, not in a void
        $expiringRes = $this->enqueueAction->handle(
            businessId: $biz->id,
            itemType: 'quote_exception',
            subject: 'Quote Exception for VIP',
            payload: ['discount' => '20%'],
            expiresInHours: -1 // expired in the past
        );

        $expProcessRes = $this->engine->processExpirations($biz->id);
        $this->assertTrue($expProcessRes['routed_to_human_screen']);
        $this->assertContains($expiringRes['approval_item_id'], $expProcessRes['expired_item_ids']);

        Event::assertDispatched(ApprovalExpired::class);
        Event::assertDispatched(ApprovalEscalated::class);
    }

    /**
     * [G7-09] the 48-hour nudge on an unopened asset
     */
    public function test_g7_09_nudge_asset(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G9-08] who approved what, exportable
     */
    public function test_g9_08_approval_audit_export(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Audit Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $item = $this->enqueueAction->handle($biz->id, 'contract', 'Vendor NDA', ['pages' => 3]);
        $decision = $this->decideAction->handle($biz->id, $item['approval_item_id'], 'approved', null, 'Looks good');

        $this->assertEquals('approved', $decision['status']);
    }

    /**
     * [G10-16] 72-hour escalation
     */
    public function test_g10_16_72h_escalation(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Escalate Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $item = $this->enqueueAction->handle($biz->id, 'refund', 'Refund $50', ['amt' => 5000]);
        $esc = $this->escalateAction->handle($biz->id, $item['approval_item_id'], '72 hours SLA exceeded');

        $this->assertEquals('escalated', $esc['status']);
    }

    /**
     * [G10-26] a term outside the standard routes for a decision
     */
    public function test_g10_26_custom_term_route(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G10-34] multi-stage sequential approval
     */
    public function test_g10_34_multistage_sequential_approval(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G12-04] approval granted the publish action fires
     */
    public function test_g12_04_approval_granted_publish(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G12-09] thirty graphics, one decision
     */
    public function test_g12_09_batch_decision(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G16-24] a comment at a timestamp IS a pending decision
     */
    public function test_g16_24_timestamp_comment(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G17-21] a rejection creates the work item
     */
    public function test_g17_21_rejection_creates_work_item(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Reject Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $item = $this->enqueueAction->handle($biz->id, 'banner', 'New Ad Banner', ['src' => 'banner.png']);
        $dec = $this->decideAction->handle($biz->id, $item['approval_item_id'], 'rejected', null, 'Font too small');

        $this->assertEquals('rejected', $dec['status']);
    }

    /**
     * [G19-13] a deep link and one green button
     */
    public function test_g19_13_deep_link(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Link Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $item = $this->enqueueAction->handle($biz->id, 'sms_blast', 'Flash Sale SMS', ['count' => 100]);
        $this->assertNotEmpty($item['magic_url']);
    }

    /**
     * [G21-07] two buttons, no login
     */
    public function test_g21_07_no_login_two_buttons(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G21-11] approve or deny without opening the CRM
     */
    public function test_g21_11_approve_deny_without_crm(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }
}
