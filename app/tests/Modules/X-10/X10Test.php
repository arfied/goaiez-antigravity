<?php

declare(strict_types=1);

namespace Tests\Modules\X10;

use App\Models\User;
use App\Modules\X01\Events\ContactCreated;
use App\Modules\X10\Actions\LeadAssignAction;
use App\Modules\X10\Actions\LeadReassignAction;
use App\Modules\X10\Actions\RoutingRulesEnsureAction;
use App\Modules\X10\Actions\TerritoryDefineAction;
use App\Modules\X10\Actions\WidgetFallbackAction;
use App\Modules\X10\Enums\RoutingRuleType;
use App\Modules\X10\Events\LeadAssigned;
use App\Modules\X10\Events\LeadReassigned;
use App\Modules\X10\Events\TerritoryChanged;
use App\Modules\X10\Mail\LeadAssignedNotification;
use App\Modules\X10\Models\Assignment;
use App\Modules\X10\Models\RoutingRule;
use App\Modules\X10\Models\Territory;
use App\Modules\X10\Ui\RoutingRules;
use App\Modules\X10\Ui\UnassignedCount;
use App\Modules\X113\Models\StaffUser;
use App\Modules\X121\Actions\PersonUpsertAction;
use App\Modules\X121\Models\Person;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class X10Test extends TestCase
{
    use RefreshesTenantDatabase;

    private TerritoryDefineAction $territoryAction;

    private LeadAssignAction $assignAction;

    private LeadReassignAction $reassignAction;

    private WidgetFallbackAction $widgetAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->territoryAction = new TerritoryDefineAction;
        $ensureAction = new RoutingRulesEnsureAction(new DefaultsRegistry);
        $this->assignAction = new LeadAssignAction($ensureAction);
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
        $biz = TestCase::provisionTenant(['name' => 'Routing Tenant', 'currency' => 'USD']);
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

        $biz = TestCase::provisionTenant(['name' => 'Dispatch Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->artisan('defaults:sync');

        $ensureAction = new RoutingRulesEnsureAction(new DefaultsRegistry);
        $rules = $ensureAction->handle($biz->id);
        $this->assertCount(4, $rules);

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
        $this->assertEquals('returning_caller', $affinityAssign->assignment_reason);

        // 3. Zipcode / Geocode polygon routing (G2-01, G2-07, G7-22)
        $polyAssign = $this->assignAction->handle(
            businessId: $biz->id,
            leadId: 502,
            previousOwnerStaffId: null,
            zipCode: '80202'
        );
        $this->assertEquals(42, $polyAssign->assigned_staff_id);
        $this->assertEquals('territory', $polyAssign->assignment_reason);

        // Disable territory rule to test fallback to workload
        $territoryRule = $rules->where('rule_type', RoutingRuleType::TERRITORY)->first();
        $territoryRule->update(['is_active' => false]);

        // 4. Open workload balancing (G15-03)
        $workloadAssign = $this->assignAction->handle(
            businessId: $biz->id,
            leadId: 503,
            previousOwnerStaffId: null,
            zipCode: '80202',
            staffWorkloads: [101 => 12, 102 => 4, 103 => 8] // 102 has lowest load
        );
        $this->assertEquals(102, $workloadAssign->assigned_staff_id);
        $this->assertEquals('workload', $workloadAssign->assignment_reason);

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

        // 6. No match -> owner user
        $defaultAssign = $this->assignAction->handle(
            businessId: $biz->id,
            leadId: 504,
            previousOwnerStaffId: null,
            zipCode: null,
            staffWorkloads: []
        );
        $this->assertEquals($biz->owner_user_id, $defaultAssign->assigned_staff_id);
        $this->assertEquals('default_staff', $defaultAssign->assignment_reason);

        // Reordering changes the winner
        $territoryRule->update(['is_active' => true, 'priority' => 3]);
        $workloadRule = $rules->where('rule_type', RoutingRuleType::WORKLOAD)->first();
        $workloadRule->update(['priority' => 2]);

        $reorderAssign = $this->assignAction->handle(
            businessId: $biz->id,
            leadId: 505,
            previousOwnerStaffId: null,
            zipCode: '80202',
            staffWorkloads: [101 => 12, 102 => 4, 103 => 8]
        );
        $this->assertEquals(102, $reorderAssign->assigned_staff_id);
        $this->assertEquals('workload', $reorderAssign->assignment_reason);
    }

    public function test_routing_rules_screen(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Routing Screen Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->artisan('defaults:sync');
        $ensureAction = new RoutingRulesEnsureAction(new DefaultsRegistry);
        $ensureAction->handle($biz->id);

        $staffUser = StaffUser::create([
            'business_id' => $biz->id,
            'name' => 'Alice Staff',
            'email' => 'alice@example.com',
            'is_active' => true,
        ]);

        Livewire::actingAs($biz->owner)
            ->test(RoutingRules::class, ['businessId' => $biz->id])
            ->assertOk()
            ->assertSee('Lead Routing Rules')
            ->call('toggle', 'returning_caller')
            ->call('moveDown', 'returning_caller')
            ->call('moveUp', 'workload')
            ->call('setDefaultStaff', $staffUser->id)
            ->assertOk();

        $this->assertDatabaseHas('routing_rules', [
            'business_id' => $biz->id,
            'rule_type' => 'returning_caller',
            'is_active' => false,
        ]);
    }

    public function test_listener_routes_contact_and_updates_unassigned_count(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Listener Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->artisan('defaults:sync');
        $ensureAction = new RoutingRulesEnsureAction(new DefaultsRegistry);
        $ensureAction->handle($biz->id);

        Livewire::test(UnassignedCount::class, ['businessId' => $biz->id])
            ->assertSee('0 unassigned leads');

        Event::dispatch(new ContactCreated($biz->id, 999, 'Test Contact'));

        $assignment = Assignment::where('business_id', $biz->id)
            ->where('lead_id', 999)
            ->first();

        $this->assertNotNull($assignment);
        $this->assertEquals('default_staff', $assignment->assignment_reason);

        // Idempotent test
        Event::dispatch(new ContactCreated($biz->id, 999, 'Test Contact'));
        $this->assertEquals(1, Assignment::where('lead_id', 999)->count());

        $assignment->update(['status' => 'closed']);

        // Returning caller affinity
        Event::dispatch(new ContactCreated($biz->id, 999, 'Test Contact'));
        $newAssignment = Assignment::where('business_id', $biz->id)
            ->where('lead_id', 999)
            ->where('status', 'active')
            ->first();
        $this->assertEquals('returning_caller', $newAssignment->assignment_reason);
        $this->assertEquals($assignment->assigned_staff_id, $newAssignment->assigned_staff_id);

        // Workload picks the lightest
        Assignment::create(['business_id' => $biz->id, 'lead_id' => 1000, 'assigned_staff_id' => $biz->owner_user_id, 'status' => 'active', 'assignment_reason' => 'workload']);
        Assignment::create(['business_id' => $biz->id, 'lead_id' => 1001, 'assigned_staff_id' => 101, 'status' => 'active', 'assignment_reason' => 'workload']);
        Assignment::create(['business_id' => $biz->id, 'lead_id' => 1002, 'assigned_staff_id' => 101, 'status' => 'active', 'assignment_reason' => 'workload']);
        Assignment::create(['business_id' => $biz->id, 'lead_id' => 1003, 'assigned_staff_id' => 102, 'status' => 'active', 'assignment_reason' => 'workload']);

        RoutingRule::where('business_id', $biz->id)
            ->whereIn('rule_type', ['returning_caller', 'territory'])
            ->update(['is_active' => false]);

        Event::dispatch(new ContactCreated($biz->id, 889, 'Workload Contact 2'));
        $workloadAssignment = Assignment::where('business_id', $biz->id)
            ->where('lead_id', 889)
            ->first();

        $this->assertEquals(102, $workloadAssignment->assigned_staff_id);
        $this->assertEquals('workload', $workloadAssignment->assignment_reason);

        Livewire::test(UnassignedCount::class, ['businessId' => $biz->id])
            ->assertSee('0 unassigned leads');

        Assignment::create(['business_id' => $biz->id, 'lead_id' => 9999, 'assigned_staff_id' => 102, 'status' => 'closed', 'assignment_reason' => 'workload']);

        Livewire::test(UnassignedCount::class, ['businessId' => $biz->id])
            ->assertSee('1 unassigned leads');
    }

    public function test_the_assigned_person_is_emailed_when_a_lead_is_routed(): void
    {
        Mail::fake();
        $biz = TestCase::provisionTenant(['name' => 'Listener Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->artisan('defaults:sync');
        $ensureAction = new RoutingRulesEnsureAction(new DefaultsRegistry);
        $ensureAction->handle($biz->id);

        $upsert = app(PersonUpsertAction::class)->upsertByPhone($biz->id, '+15125567731', ['first_name' => 'Distinctive', 'email' => null], false);
        Event::dispatch(new ContactCreated($biz->id, (int) $upsert['id'], 'Distinctive'));

        Mail::assertSentCount(1);
        Mail::assertSent(LeadAssignedNotification::class, function ($mail) use ($biz) {
            return $mail->hasTo(User::query()->whereKey($biz->owner_user_id)->value('email')) && $mail->leadName === 'Distinctive';
        });
    }

    public function test_no_email_when_the_assignee_has_no_address(): void
    {
        Mail::fake();
        $biz = TestCase::provisionTenant(['name' => 'Listener Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->artisan('defaults:sync');
        $ensureAction = new RoutingRulesEnsureAction(new DefaultsRegistry);
        $ensureAction->handle($biz->id);

        Event::dispatch(new LeadAssigned($biz->id, 999, 999999, 'default_staff'));
        Mail::assertNothingSent();
    }
}
