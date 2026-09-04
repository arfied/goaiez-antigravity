<?php

declare(strict_types=1);

namespace Tests\Modules\X145;

use App\Modules\X145\Actions\DecisionExplainAction;
use App\Modules\X145\Actions\DecisionGradeOutcomeAction;
use App\Modules\X145\Actions\DecisionProposeAction;
use App\Modules\X145\Events\ApprovalRequested;
use App\Modules\X145\Events\DecisionProposed;
use App\Modules\X145\Models\Decision;
use App\Modules\X145\Models\DecisionOutcome;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X145Test extends TestCase
{
    private DecisionProposeAction $proposeAction;

    private DecisionExplainAction $explainAction;

    private DecisionGradeOutcomeAction $gradeAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proposeAction = new DecisionProposeAction;
        $this->explainAction = new DecisionExplainAction;
        $this->gradeAction = new DecisionGradeOutcomeAction;
    }

    /**
     * TEST ANCHOR
     * a terminal action never appears in a proposal set;
     * every proposal row carries a non-empty explanation built from named entity fields;
     * a proposal is graded by its outcome event within the window, either way
     */
    public function test_anchor_terminal_action_refusal_named_explanation_and_outcome_grading(): void
    {
        Event::fake([DecisionProposed::class, ApprovalRequested::class]);

        $biz = TestCase::provisionTenant(['name' => 'Decision Proposal Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $namedEntityFields = [
            'name' => 'Alice Johnson',
            'service' => 'Commercial AC Tune-Up',
            'reason' => 'High ambient temperature forecast next week',
        ];

        // 1. Terminal action NEVER appears in a proposal set (TEST ANCHOR)
        $terminalResult = $this->proposeAction->propose(
            businessId: $biz->id,
            targetEntityType: 'job',
            targetEntityId: 501,
            proposedAction: 'job.canceled', // Terminal action
            namedEntityFields: $namedEntityFields
        );

        $this->assertEquals('refused', $terminalResult['status']);
        $this->assertEquals('TERMINAL_ACTION_CANNOT_BE_PROPOSED', $terminalResult['refusal_code']);
        $this->assertNull($terminalResult['decision']);

        $refusedCount = Decision::where('business_id', $biz->id)->where('proposed_action', 'job.canceled')->count();
        $this->assertEquals(0, $refusedCount, 'Terminal actions never appear in decision proposals table');

        // 2. Valid proposal carries a NON-EMPTY explanation built from named entity fields (TEST ANCHOR)
        $validResult = $this->proposeAction->propose(
            businessId: $biz->id,
            targetEntityType: 'job',
            targetEntityId: 502,
            proposedAction: 'technician.dispatch_priority',
            namedEntityFields: $namedEntityFields,
            requiresApproval: true
        );

        $this->assertEquals('proposed', $validResult['status']);
        $this->assertNotEmpty($validResult['explanation']);
        $this->assertStringContainsString('Alice Johnson', $validResult['explanation']);
        $this->assertStringContainsString('Commercial AC Tune-Up', $validResult['explanation']);
        $this->assertStringContainsString('High ambient temperature', $validResult['explanation']);

        $savedDecision = Decision::where('business_id', $biz->id)->find($validResult['decision_id']);
        $this->assertNotNull($savedDecision);
        $this->assertEquals($validResult['explanation'], $savedDecision->explanation);

        Event::assertDispatched(DecisionProposed::class);
        Event::assertDispatched(ApprovalRequested::class);

        // 3. A proposal is graded by its outcome event within the window, either way (TEST ANCHOR)
        $outcomeFavorable = $this->gradeAction->grade(
            businessId: $biz->id,
            decisionId: $savedDecision->id,
            outcomeEvent: 'job.completed_5_star_review',
            isFavorable: true
        );

        $this->assertTrue($outcomeFavorable->is_favorable);
        $this->assertEquals('job.completed_5_star_review', $outcomeFavorable->outcome_event);

        $savedOutcome = DecisionOutcome::where('business_id', $biz->id)->where('decision_id', $savedDecision->id)->first();
        $this->assertNotNull($savedOutcome);
        $this->assertTrue($savedOutcome->is_favorable);
    }

    /**
     * [N-145-01]
     */
    public function test_n_145_capabilities(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }
}
