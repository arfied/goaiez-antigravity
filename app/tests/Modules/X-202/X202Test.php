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
use App\Modules\X202\Models\ApprovalChain;
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
        Event::fake([ApprovalRaised::class, ApprovalDecided::class, ApprovalExpired::class, ApprovalEscalated::class]);
        $biz = TestCase::provisionTenant(['name' => 'Custom Term Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $item = $this->enqueueAction->handle(
            businessId: $biz->id,
            itemType: 'bespoke_msa_clause',
            subject: 'Special MSA for Enterprise Client',
            payload: ['clause' => 'Net 90']
        );

        $this->assertEquals('enqueued', $item['status']);
        Event::assertDispatched(ApprovalRaised::class, function ($e) use ($item) {
            return $e->itemType === 'bespoke_msa_clause' && $e->approvalItemId === $item['approval_item_id'];
        });

        $dec = $this->decideAction->handle($biz->id, $item['approval_item_id'], 'approved');
        $this->assertEquals('approved', $dec['status']);

        $badItem = $this->enqueueAction->handle(
            businessId: $biz->id,
            itemType: 'bespoke_msa_clause_bad',
            subject: 'Bad MSA',
            payload: ['error_only' => true]
        );
        $this->assertEquals('refused', $badItem['status']);
        $this->assertEquals('INVALID_APPROVAL_PAYLOAD', $badItem['refusal_code']);
        $this->assertSame(0, ApprovalItem::where('item_type', 'bespoke_msa_clause_bad')->count());
    }

    /**
     * [G10-34] multi-stage sequential approval
     */
    public function test_g10_34_multistage_sequential_approval(): void
    {
        Event::fake([ApprovalRaised::class, ApprovalDecided::class, ApprovalExpired::class, ApprovalEscalated::class]);

        $biz = TestCase::provisionTenant(['name' => 'Chain Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $solo = $this->engine->enqueue($biz->id, 'creative', 'One-hop asset', ['asset_id' => 1]);
        $soloDec = $this->decideAction->handle($biz->id, $solo['approval_item_id'], 'approved');
        $this->assertSame('approved', $soloDec['status'], 'an item with no chain still decides in a single hop');
        Event::assertDispatchedTimes(ApprovalDecided::class, 1);

        $chain = ApprovalChain::create([
            'business_id' => $biz->id,
            'name' => 'Three-desk sequential',
            'steps_count' => 3,
            'chain_config' => ['steps' => ['designer', 'manager', 'owner']],
        ]);

        $chained = $this->engine->enqueue($biz->id, 'creative', 'Chained asset', ['asset_id' => 2]);
        ApprovalItem::where('id', $chained['approval_item_id'])->update(['approval_chain_id' => $chain->id]);

        $step1 = $this->decideAction->handle($biz->id, $chained['approval_item_id'], 'approved');
        $this->assertSame('pending', $step1['status'], 'step 1 of 3 does not decide the item');
        $this->assertSame(2, $step1['current_step'], 'an approval at step 1 advances the chain to step 2');
        Event::assertDispatchedTimes(ApprovalDecided::class, 1);

        $step2 = $this->decideAction->handle($biz->id, $chained['approval_item_id'], 'approved');
        $this->assertSame('pending', $step2['status'], 'step 2 of 3 does not decide it either');
        $this->assertSame(3, $step2['current_step'], 'an approval at step 2 advances the chain to step 3');
        Event::assertDispatchedTimes(ApprovalDecided::class, 1);

        $step3 = $this->decideAction->handle($biz->id, $chained['approval_item_id'], 'approved');
        $this->assertSame('approved', $step3['status'], 'the last step of the chain is the one that approves');
        $this->assertSame('approved', ApprovalItem::find($chained['approval_item_id'])->status, 'the terminal status reaches the row');
        Event::assertDispatchedTimes(ApprovalDecided::class, 2);

        $rej = $this->engine->enqueue($biz->id, 'creative', 'Rejected at step 1', ['asset_id' => 3]);
        ApprovalItem::where('id', $rej['approval_item_id'])->update(['approval_chain_id' => $chain->id]);
        $rejDec = $this->decideAction->handle($biz->id, $rej['approval_item_id'], 'rejected', null, 'Off-brand');
        $this->assertSame('rejected', $rejDec['status'], 'a rejection ends the chain at the step it arrives on');
        $this->assertSame(1, ApprovalItem::find($rej['approval_item_id'])->current_step, 'a rejection does not advance the chain');
        Event::assertDispatchedTimes(ApprovalDecided::class, 3);
    }

    /**
     * [G12-09] thirty graphics, one decision
     */
    public function test_g12_09_batch_decision(): void
    {
        Event::fake([ApprovalRaised::class, ApprovalDecided::class, ApprovalExpired::class, ApprovalEscalated::class]);
        $biz = TestCase::provisionTenant(['name' => 'Batch Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $id1 = $this->enqueueAction->handle($biz->id, 'creative', 'Item 1', ['a' => 1])['approval_item_id'];
        $id2 = $this->enqueueAction->handle($biz->id, 'creative', 'Item 2', ['a' => 2])['approval_item_id'];
        $id3 = $this->enqueueAction->handle($biz->id, 'creative', 'Item 3', ['a' => 3])['approval_item_id'];
        $id4 = $this->enqueueAction->handle($biz->id, 'creative', 'Item 4', ['a' => 4])['approval_item_id'];
        $id5 = $this->enqueueAction->handle($biz->id, 'creative', 'Item 5', ['a' => 5], 'L1', true)['approval_item_id'];

        $res = $this->engine->batchApprove($biz->id, [$id1, $id2, $id3, $id4, $id5]);

        $this->assertSame(4, $res['approved_count']);
        $this->assertSame([$id5], $res['skipped_l1_forever_ids']);
        Event::assertDispatchedTimes(ApprovalDecided::class, 4);
    }

    /**
     * [G12-04] approval granted → the publish action fires
     */
    public function test_g12_04_publish_authorization_floor(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Publish Auth Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. A plain item approved terminally authorizes
        $plainItem = $this->engine->enqueue($biz->id, 'creative', 'Plain item', ['a' => 1], 'L2', false);
        $plainDec = $this->decideAction->handle($biz->id, $plainItem['approval_item_id'], 'approved');
        $this->assertTrue($plainDec['publish_authorized']);

        // 2. An is_l1_forever item approved terminally does not
        $l1Item = $this->engine->enqueue($biz->id, 'creative', 'L1 item', ['a' => 1], 'L1', true);
        $l1Dec = $this->decideAction->handle($biz->id, $l1Item['approval_item_id'], 'approved');
        $this->assertFalse($l1Dec['publish_authorized']);

        // 3. A chained item at step 1 of 3 does not
        $chain = ApprovalChain::create([
            'business_id' => $biz->id,
            'name' => 'Three-desk sequential',
            'steps_count' => 3,
            'chain_config' => ['steps' => ['designer', 'manager', 'owner']],
        ]);
        $chained = $this->engine->enqueue($biz->id, 'creative', 'Chained item', ['a' => 1], 'L2', false);
        ApprovalItem::where('id', $chained['approval_item_id'])->update(['approval_chain_id' => $chain->id]);

        $chainedDec = $this->decideAction->handle($biz->id, $chained['approval_item_id'], 'approved');
        $this->assertSame('pending', $chainedDec['status']);
        $this->assertFalse($chainedDec['publish_authorized']);

        // 4. A rejection never authorizes
        $rejItem = $this->engine->enqueue($biz->id, 'creative', 'Reject item', ['a' => 1], 'L2', false);
        $rejDec = $this->decideAction->handle($biz->id, $rejItem['approval_item_id'], 'rejected');
        $this->assertFalse($rejDec['publish_authorized']);
    }

    /**
     * [G16-24] a comment at a timestamp IS a pending decision
     */
    public function test_g16_24_timestamp_comment(): void
    {
        Event::fake([ApprovalRaised::class, ApprovalDecided::class, ApprovalExpired::class, ApprovalEscalated::class]);

        $biz = TestCase::provisionTenant(['name' => 'Comment Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $chain = ApprovalChain::create([
            'business_id' => $biz->id,
            'name' => 'Three-desk sequential',
            'steps_count' => 3,
            'chain_config' => ['steps' => ['designer', 'manager', 'owner']],
        ]);

        $chained = $this->engine->enqueue($biz->id, 'creative', 'Chained asset with comments', ['asset_id' => 10]);
        ApprovalItem::where('id', $chained['approval_item_id'])->update(['approval_chain_id' => $chain->id]);

        $step1 = $this->decideAction->handle($biz->id, $chained['approval_item_id'], 'approved', null, 'fine by me, over to legal');
        $this->assertSame('pending', $step1['status']);

        $itemFresh = ApprovalItem::find($chained['approval_item_id']);
        $this->assertSame('pending', $itemFresh->status);
        $this->assertNull($itemFresh->decided_at);
        $this->assertStringContainsString('fine by me, over to legal', $itemFresh->decision_comment);

        $step2 = $this->decideAction->handle($biz->id, $chained['approval_item_id'], 'approved', null, 'looks ok');
        $this->assertSame('pending', $step2['status']);

        $itemFresh2 = ApprovalItem::find($chained['approval_item_id']);
        $this->assertSame('pending', $itemFresh2->status);
        $this->assertNull($itemFresh2->decided_at);
        $this->assertStringContainsString('fine by me, over to legal', $itemFresh2->decision_comment);
        $this->assertStringContainsString('looks ok', $itemFresh2->decision_comment);

        $step3 = $this->decideAction->handle($biz->id, $chained['approval_item_id'], 'approved', null, 'approved to go');
        $this->assertSame('approved', $step3['status']);

        $itemFresh3 = ApprovalItem::find($chained['approval_item_id']);
        $this->assertSame('approved', $itemFresh3->status);
        $this->assertNotNull($itemFresh3->decided_at);
        $this->assertStringContainsString('fine by me, over to legal', $itemFresh3->decision_comment);
        $this->assertStringContainsString('looks ok', $itemFresh3->decision_comment);
        $this->assertStringContainsString('approved to go', $itemFresh3->decision_comment);

        Event::assertDispatchedTimes(ApprovalDecided::class, 1);
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
     * UNRESOLVED — no route consumes magic_url
     */
    public function test_g21_07_no_login_two_buttons(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Token Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $item1 = $this->enqueueAction->handle($biz->id, 'creative', 'Item 1', ['a' => 1]);
        $item2 = $this->enqueueAction->handle($biz->id, 'creative', 'Item 2', ['a' => 2]);
        $item3 = $this->enqueueAction->handle($biz->id, 'creative', 'Item 3', ['a' => 3]);

        $tokens = array_unique([$item1['item']->magic_token, $item2['item']->magic_token, $item3['item']->magic_token]);

        $this->assertCount(3, $tokens);
        $this->assertSame(32, strlen($item1['item']->magic_token));
        $this->assertSame(32, strlen($item2['item']->magic_token));
        $this->assertSame(32, strlen($item3['item']->magic_token));

        $retrieved = ApprovalItem::where('magic_token', $item2['item']->magic_token)->first();
        $this->assertSame($item2['approval_item_id'], $retrieved->id);
    }

    /**
     * [G21-11] approve or deny without opening the CRM
     */
    public function test_g21_11_approve_deny_without_crm(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'CRM Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $item = $this->enqueueAction->handle($biz->id, 'creative', 'Item', ['a' => 1]);
        $dec = $this->decideAction->handle($biz->id, $item['approval_item_id'], 'approved', null, null);

        $this->assertSame('approved', $dec['status']);
        $fresh = ApprovalItem::find($item['approval_item_id']);
        $this->assertNull($fresh->decided_by_user_id);
    }
}
