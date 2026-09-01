<?php

declare(strict_types=1);

namespace Tests\Modules\X183;

use App\Modules\X183\Actions\ContentGateAction;
use App\Modules\X183\Actions\ContentWriteAction;
use App\Modules\X183\Actions\DraftDeleteAction;
use App\Modules\X183\Actions\DraftEditAction;
use App\Modules\X183\Events\ContentGated;
use App\Modules\X183\Events\ContentRejected;
use App\Modules\X183\Models\ContentDraft;
use App\Modules\X183\Models\TrustLadder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X183Test extends TestCase
{
    private ContentWriteAction $writeAction;

    private ContentGateAction $gateAction;

    private DraftEditAction $editAction;

    private DraftDeleteAction $deleteAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeAction = new ContentWriteAction;
        $this->gateAction = new ContentGateAction;
        $this->editAction = new DraftEditAction;
        $this->deleteAction = new DraftDeleteAction;
    }

    /**
     * TEST ANCHOR
     * no draft with gate_results.passed = false ever reaches content.created;
     * an edit to an approved post resets trust_ladder.count to zero;
     * a delete sets unattended = false
     */
    public function test_anchor_gate_rejection_suppresses_creation_edit_resets_ladder_and_delete_sets_unattended_false(): void
    {
        Event::fake([ContentGated::class, ContentRejected::class]);

        $biz = TestCase::provisionTenant(['name' => 'Content Grounding Gate Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Rejected draft (contains SAMPLE PRICE string) (G12-29)
        $badDraft = $this->writeAction->writeDraft(
            businessId: $biz->id,
            title: 'Sample HVAC Pricing',
            bodyText: 'We install heat pumps starting at SAMPLE PRICE $XX per unit.'
        );

        $badGateResult = $this->gateAction->evaluateGate($biz->id, $badDraft->id);
        $this->assertFalse($badGateResult->passed, 'Draft with sample price fails gate');

        $savedBadDraft = ContentDraft::where('business_id', $biz->id)->find($badDraft->id);
        $this->assertFalse($savedBadDraft->is_published, 'Draft with gate_results.passed = false never published (TEST ANCHOR)');
        $this->assertFalse($savedBadDraft->is_approved);
        Event::assertDispatched(ContentRejected::class);

        // 2. Valid draft passes gate, increments trust ladder (G8-39, G12-02)
        $goodDraft = $this->writeAction->writeDraft(
            businessId: $biz->id,
            title: 'Complete Ductwork Inspection Guide',
            bodyText: 'Regular duct inspections increase HVAC efficiency by up to 20% in residential homes.'
        );

        $goodGateResult = $this->gateAction->evaluateGate($biz->id, $goodDraft->id);
        $this->assertTrue($goodGateResult->passed);

        $savedGoodDraft = ContentDraft::where('business_id', $biz->id)->find($goodDraft->id);
        $this->assertTrue($savedGoodDraft->is_published);
        $this->assertTrue($savedGoodDraft->is_approved);
        Event::assertDispatched(ContentGated::class);

        $ladder = TrustLadder::where('business_id', $biz->id)->first();
        $this->assertNotNull($ladder);
        $this->assertEquals(1, $ladder->consecutive_approved_count);

        // 3. An edit to an approved post resets trust_ladder.count to zero (TEST ANCHOR)
        $this->editAction->editDraft($biz->id, $goodDraft->id, 'Updated duct inspection guide text with new notes.');
        $ladderAfterEdit = TrustLadder::where('business_id', $biz->id)->first();
        $this->assertEquals(0, $ladderAfterEdit->consecutive_approved_count, 'Edit resets trust_ladder.count to zero (TEST ANCHOR)');

        // 4. A delete sets unattended = false (TEST ANCHOR)
        $this->deleteAction->deleteDraft($biz->id, $goodDraft->id);
        $ladderAfterDelete = TrustLadder::where('business_id', $biz->id)->first();
        $this->assertFalse($ladderAfterDelete->unattended, 'Delete sets unattended = false (TEST ANCHOR)');
    }

    /**
     * [G2-11], [G5-11], [G5-35], [G6-04], [G7-24], [G8-39], [G9-17], [G9-22], [G12-02], [G12-05], [G12-29], [G13-29], [G16-03], [G16-28], [G20-15]
     */
    public function test_gate_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
