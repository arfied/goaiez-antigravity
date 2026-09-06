<?php

namespace Tests\Modules\X183;

use App\Modules\X183\Domain\GateEngine;
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
}
