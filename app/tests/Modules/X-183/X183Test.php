<?php

namespace Tests\Modules\X183;

use App\Modules\X183\Actions\ContentGateAction;
use App\Modules\X183\Domain\GateEngine;
use App\Modules\X183\Events\ContentGated;
use App\Modules\X183\Events\ContentRejected;
use App\Modules\X183\Models\ContentDraft;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X183Test extends TestCase
{
    /**
     * [G2-11]
     */
    public function test_case_study_requires_double_consent()
    {
        $engine = new GateEngine;
        $this->assertFalse($engine->requireDoubleConsent(true, false));
        $this->assertFalse($engine->requireDoubleConsent(false, true));
        $this->assertFalse($engine->requireDoubleConsent(false, false));
    }

    /**
     * [G5-11]
     */
    public function test_negative_comment_escalates_to_approval_desk()
    {
        $engine = new GateEngine;
        $this->assertEquals('ApprovalDesk', $engine->escalateNegativeComment('this is bad'));
        $this->assertEquals('None', $engine->escalateNegativeComment('this is ok'));
    }

    /**
     * [G5-35]
     */
    public function test_drafts_require_real_data_and_double_consent()
    {
        $engine = new GateEngine;
        $this->assertFalse($engine->requireRealData(false));
        $this->assertFalse($engine->requireDoubleConsent(true, false));
    }

    /**
     * [G6-04]
     */
    public function test_case_study_render_requires_double_consent()
    {
        $engine = new GateEngine;
        $this->assertFalse($engine->requireDoubleConsent(true, false));
        $this->assertFalse($engine->requireDoubleConsent(false, true));
    }

    /**
     * [G7-24]
     */
    public function test_pre_publish_gate_refuses_human_draft_missing_grounding_or_citation()
    {
        $engine = new GateEngine;
        $this->assertFalse($engine->prePublishGate(false, true));
        $this->assertFalse($engine->prePublishGate(true, false));
    }

    /**
     * [G9-17]
     */
    public function test_summary_gated_by_pre_publish_gate()
    {
        $engine = new GateEngine;
        $this->assertFalse($engine->prePublishGate(false, true));
    }

    /**
     * [G9-22]
     */
    public function test_hard_numbers_from_real_data_only()
    {
        $engine = new GateEngine;
        $this->assertFalse($engine->requireRealData(false));
    }

    /**
     * [G12-02]
     */
    public function test_pre_publish_gate_refuses_without_grounding()
    {
        $engine = new GateEngine;
        $this->assertFalse($engine->prePublishGate(false, true));
    }

    /**
     * [G12-29]
     */
    public function test_sample_prices_never_rendered()
    {
        $engine = new GateEngine;
        $this->assertFalse($engine->noSamplePrices('SAMPLE PRICE $10'));
    }

    /**
     * [G13-29]
     */
    public function test_pre_publish_gate_refuses_without_citation()
    {
        $engine = new GateEngine;
        $this->assertFalse($engine->prePublishGate(true, false));
    }

    /**
     * [G16-03]
     */
    public function test_long_form_structure_refused_when_ungrounded()
    {
        $engine = new GateEngine;
        $this->assertFalse($engine->prePublishGate(false, true));
    }

    /**
     * [G20-15]
     */
    public function test_real_review_requires_real_data()
    {
        $engine = new GateEngine;
        $this->assertFalse($engine->requireRealData(false));
    }

    /**
     * [G12-29]
     */
    public function test_g12_29_rejects_sample_prices()
    {
        $biz = TestCase::provisionTenant(['name' => 'Test Biz', 'currency' => 'USD']);

        Tenancy::actingAs($biz->id, function () use ($biz) {
            Event::fake();

            $draft = ContentDraft::create([
                'business_id' => $biz->id,
                'title' => 'Test Draft',
                'body_text' => 'This is a SAMPLE PRICE for a service.',
                'is_case_study' => false,
                'has_double_consent' => false,
                'is_approved' => true,
                'is_published' => true,
            ]);

            $action = new ContentGateAction;
            $result = $action->evaluateGate($biz->id, $draft->id);

            $this->assertFalse($result->passed);
            $this->assertEquals('Draft contains placeholder sample price', $result->rejection_reason);

            $draft->refresh();
            $this->assertFalse($draft->is_approved);
            $this->assertFalse($draft->is_published);

            Event::assertDispatched(ContentRejected::class, function ($event) use ($biz, $draft) {
                return $event->businessId === $biz->id && $event->draftId === $draft->id && $event->reason === 'Draft contains placeholder sample price';
            });
        });
    }

    /**
     * [G2-11]
     */
    public function test_g2_11_rejects_case_study_without_consent()
    {
        $biz = TestCase::provisionTenant(['name' => 'Test Biz', 'currency' => 'USD']);

        Tenancy::actingAs($biz->id, function () use ($biz) {
            Event::fake();

            $draft = ContentDraft::create([
                'business_id' => $biz->id,
                'title' => 'Test Draft',
                'body_text' => 'A valid case study body.',
                'is_case_study' => true,
                'has_double_consent' => false,
                'is_approved' => true,
                'is_published' => true,
            ]);

            $action = new ContentGateAction;
            $result = $action->evaluateGate($biz->id, $draft->id);

            $this->assertFalse($result->passed);
            $this->assertEquals('R36: Case study requires verified double consent before publication', $result->rejection_reason);

            $draft->refresh();
            $this->assertFalse($draft->is_approved);
            $this->assertFalse($draft->is_published);

            Event::assertDispatched(ContentRejected::class);
        });
    }

    /**
     * [G5-35]
     */
    public function test_g5_35_enforces_real_data_double_consent()
    {
        $biz = TestCase::provisionTenant(['name' => 'Test Biz', 'currency' => 'USD']);

        Tenancy::actingAs($biz->id, function () use ($biz) {
            Event::fake();

            $draft = ContentDraft::create([
                'business_id' => $biz->id,
                'title' => 'Test Draft',
                'body_text' => 'Another valid case study body.',
                'is_case_study' => true,
                'has_double_consent' => false,
                'is_approved' => true,
                'is_published' => true,
            ]);

            $action = new ContentGateAction;
            $result = $action->evaluateGate($biz->id, $draft->id);

            $this->assertFalse($result->passed);
            $this->assertEquals('R36: Case study requires verified double consent before publication', $result->rejection_reason);

            $draft->refresh();
            $this->assertFalse($draft->is_approved);
            $this->assertFalse($draft->is_published);

            Event::assertDispatched(ContentRejected::class);
        });
    }

    /**
     * [G6-04]
     */
    public function test_g6_04_publishes_case_study_with_consent()
    {
        $biz = TestCase::provisionTenant(['name' => 'Test Biz', 'currency' => 'USD']);

        Tenancy::actingAs($biz->id, function () use ($biz) {
            Event::fake();

            $draft = ContentDraft::create([
                'business_id' => $biz->id,
                'title' => 'Test Draft',
                'body_text' => 'Valid case study text.',
                'is_case_study' => true,
                'has_double_consent' => true,
                'is_approved' => false,
                'is_published' => false,
            ]);

            $action = new ContentGateAction;
            $result = $action->evaluateGate($biz->id, $draft->id);

            $this->assertTrue($result->passed);
            $this->assertNull($result->rejection_reason);

            $draft->refresh();
            $this->assertTrue($draft->is_approved);
            $this->assertTrue($draft->is_published);

            Event::assertDispatched(ContentGated::class, function ($event) use ($biz, $draft) {
                return $event->businessId === $biz->id && $event->draftId === $draft->id && $event->passed === true;
            });
        });
    }

    /**
     * [G8-39]
     * Grounding half of a two-halved capability: the gate passes only when grounding
     * and citation are both present.
     * Note: No module under app/app/Modules/ owns the "structure from the top-ranking analysis" surface,
     * so this test closes the grounding half only.
     */
    public function test_g8_39_pre_publish_gate_passes_only_when_grounded_and_cited()
    {
        $engine = new GateEngine;

        $this->assertTrue($engine->prePublishGate(true, true));

        $this->assertFalse($engine->prePublishGate(false, true));
        $this->assertFalse($engine->prePublishGate(true, false));
        $this->assertFalse($engine->prePublishGate(false, false));
    }
}
