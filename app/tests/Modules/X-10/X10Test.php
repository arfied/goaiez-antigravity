<?php

declare(strict_types=1);

namespace Tests\Modules\X10;

use App\Modules\X10\Actions\LeadAssignAction;
use App\Modules\X10\Actions\LeadReassignAction;
use App\Modules\X10\Actions\TerritoryDefineAction;
use App\Modules\X10\Actions\WidgetFallbackAction;
use App\Modules\X10\Events\LeadAssigned;
use App\Modules\X10\Events\LeadReassigned;
use App\Modules\X10\Events\TerritoryChanged;
use App\Modules\X10\Models\Territory;
use App\Modules\X121\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X10Test extends TestCase
{
    private TerritoryDefineAction $territoryAction;

    private LeadAssignAction $assignAction;

    private LeadReassignAction $reassignAction;

    private WidgetFallbackAction $widgetAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->territoryAction = new TerritoryDefineAction;
        $this->assignAction = new LeadAssignAction;
        $this->reassignAction = new LeadReassignAction;
        $this->widgetAction = new WidgetFallbackAction;
    }

    /**
     * TEST ANCHOR
     * with the AI-credit cap reached the widget renders the form and the form submission creates the Person;
     * a question with no grounding Fact gets the refusal string, not an answer; four rage-clicks escalate
     */
    public function test_anchor_widget_credit_cap_fallback_no_fact_refusal_and_rage_clicks(): void
    {
        $biz = \Tests\TestCase::provisionTenant(['name' => 'Routing Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Credit cap reached -> renders fallback form (TEST ANCHOR)
        $formPromptRes = $this->widgetAction->handleChatOrFallback(
            businessId: $biz->id,
            isCreditCapReached: true,
            factFound: null,
            rageClickCount: 0,
            fallbackFormData: null
        );
        $this->assertEquals('render_fallback_form', $formPromptRes['status']);

        // Form submission creates the Person (TEST ANCHOR)
        $formSubmitRes = $this->widgetAction->handleChatOrFallback(
            businessId: $biz->id,
            isCreditCapReached: true,
            factFound: null,
            rageClickCount: 0,
            fallbackFormData: ['name' => 'Alice Walker', 'phone' => '+15554443333', 'email' => 'alice@example.com']
        );
        $this->assertEquals('fallback_form_submitted', $formSubmitRes['status']);
        $this->assertTrue($formSubmitRes['person_created']);

        $person = Person::where('business_id', $biz->id)->find($formSubmitRes['person_id']);
        $this->assertNotNull($person);
        $this->assertEquals('Alice', $person->first_name);
        $this->assertEquals('Walker', $person->last_name);

        // 2. Question with no grounding Fact gets the refusal string, not an answer (TEST ANCHOR)
        $noFactRes = $this->widgetAction->handleChatOrFallback(
            businessId: $biz->id,
            isCreditCapReached: false,
            factFound: null,
            rageClickCount: 0
        );
        $this->assertEquals('refused', $noFactRes['status']);
        $this->assertEquals('NO_FACT_GROUNDING', $noFactRes['refusal_code']);
        $this->assertStringContainsString('no verified business facts exist', $noFactRes['answer']);

        // 3. Four rage-clicks escalate immediately (TEST ANCHOR)
        $rageRes = $this->widgetAction->handleChatOrFallback(
            businessId: $biz->id,
            isCreditCapReached: false,
            factFound: 'Some fact',
            rageClickCount: 4
        );
        $this->assertEquals('escalated_human', $rageRes['status']);
        $this->assertEquals('RAGE_CLICKS_ESCALATION', $rageRes['reason']);
    }

    /**
     * [G2-01], [G2-07], [G2-34], [G2-60], [G2-62], [G2-64], [G2-70], [G2-72], [G2-74], [G2-75], [G3-47], [G3-58], [G7-22], [G15-03], [G17-23], [G17-25]
     * Geocode territory polygon matching, returning caller affinity, workload balancing, SLA reassignment
     */
    public function test_routing_affinity_polygons_workload_and_sla_reassignment(): void
    {
        Event::fake([LeadAssigned::class, LeadReassigned::class, TerritoryChanged::class]);

        $biz = \Tests\TestCase::provisionTenant(['name' => 'Dispatch Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Territory polygon definition (G17-23)
        $territory = $this->territoryAction->handle(
            businessId: $biz->id,
            name: 'North Denver Polygon',
            assignedStaffId: 42,
            zipCodes: ['80202', '80203']
        );
        $this->assertEquals(42, $territory->assigned_staff_id);
        Event::assertDispatched(TerritoryChanged::class);

        // 2. Returning caller affinity reaches same owner (G2-74, G2-75)
        $affinityAssign = $this->assignAction->handle(
            businessId: $biz->id,
            leadId: 501,
            previousOwnerStaffId: 77
        );
        $this->assertEquals(77, $affinityAssign->assigned_staff_id);
        $this->assertEquals('returning_caller_affinity', $affinityAssign->assignment_reason);

        // 3. Zipcode / Geocode polygon routing (G2-01, G2-07, G7-22)
        $polyAssign = $this->assignAction->handle(
            businessId: $biz->id,
            leadId: 502,
            previousOwnerStaffId: null,
            zipCode: '80202'
        );
        $this->assertEquals(42, $polyAssign->assigned_staff_id);
        $this->assertEquals('polygon_territory_match', $polyAssign->assignment_reason);

        // 4. Open workload balancing (G15-03)
        $workloadAssign = $this->assignAction->handle(
            businessId: $biz->id,
            leadId: 503,
            previousOwnerStaffId: null,
            zipCode: null,
            staffWorkloads: [101 => 12, 102 => 4, 103 => 8] // 102 has lowest load
        );
        $this->assertEquals(102, $workloadAssign->assigned_staff_id);
        $this->assertEquals('workload_balanced', $workloadAssign->assignment_reason);

        // 5. SLA reassignment (G2-60, G17-25)
        $reassigned = $this->reassignAction->handle(
            businessId: $biz->id,
            assignmentId: $workloadAssign->id,
            newStaffId: 103,
            reason: 'sla_timeout'
        );
        $this->assertEquals(103, $reassigned->assigned_staff_id);
        $this->assertEquals('reassigned', $reassigned->status);
        Event::assertDispatched(LeadReassigned::class);
    }
}
