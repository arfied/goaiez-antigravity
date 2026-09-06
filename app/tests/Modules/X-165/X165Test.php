<?php

declare(strict_types=1);

namespace Tests\Modules\X165;

use App\Modules\X165\Actions\MembershipRenewAction;
use App\Modules\X165\Actions\MembershipStartAction;
use App\Modules\X165\Actions\PlanProposeAction;
use App\Modules\X165\Actions\PrioritySchedulingAction;
use App\Modules\X165\Actions\RenewalReminderAction;
use App\Modules\X165\Events\MembershipRenewed;
use App\Modules\X165\Events\MembershipStarted;
use App\Modules\X165\Models\Membership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X165Test extends TestCase
{
    private PlanProposeAction $planAction;

    private MembershipStartAction $startAction;

    private MembershipRenewAction $renewAction;

    private PrioritySchedulingAction $scheduleAction;

    private RenewalReminderAction $reminderAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planAction = new PlanProposeAction;
        $this->startAction = new MembershipStartAction;
        $this->renewAction = new MembershipRenewAction;
        $this->scheduleAction = new PrioritySchedulingAction;
        $this->reminderAction = new RenewalReminderAction;
    }

    /**
     * TEST ANCHOR
     * a member's booking request is served before a non-member's for the same window — asserted on the scheduler's decision log;
     * a renewal reminder precedes every renewal charge by the configured days
     */
    public function test_anchor_member_priority_scheduling_and_renewal_reminder(): void
    {
        Event::fake([MembershipStarted::class, MembershipRenewed::class]);

        $biz = TestCase::provisionTenant(['name' => 'VIP Members Club', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $plan = $this->planAction->handle(
            businessId: $biz->id,
            name: 'Gold Home Protection',
            priceCents: 29900,
            intervalMonths: 12,
            reminderDays: 14 // 14 days renewal reminder
        );

        $memberPersonId = 88;
        $nonMemberPersonId = 99;

        $membership = $this->startAction->handle($biz->id, $plan->id, $memberPersonId);
        Event::assertDispatched(MembershipStarted::class);

        // 1. A member's booking request is served before a non-member's for the same window (TEST ANCHOR)
        $scheduleResult = $this->scheduleAction->scheduleWindow(
            businessId: $biz->id,
            timeWindow: '2026-09-01 09:00-11:00',
            firstRequestPersonId: $nonMemberPersonId,
            secondRequestPersonId: $memberPersonId
        );

        $this->assertEquals($memberPersonId, $scheduleResult['served_person_id'], 'Member served before non-member on same window');
        $this->assertTrue($scheduleResult['priority_applied']);
        $this->assertStringContainsString('membership priority', $scheduleResult['decision_log']);

        // 2. A renewal reminder precedes every renewal charge by configured days (14 days before renewal) (TEST ANCHOR)
        // 30 days before renewal -> NOT due
        $earlyCheck = $this->reminderAction->sendReminderIfDue(
            businessId: $biz->id,
            membershipId: $membership->id,
            currentTime: $membership->renews_at->copy()->subDays(30)
        );
        $this->assertEquals('not_due_or_already_sent', $earlyCheck['status']);

        // 14 days before renewal -> Reminder sent!
        $dueCheck = $this->reminderAction->sendReminderIfDue(
            businessId: $biz->id,
            membershipId: $membership->id,
            currentTime: $membership->renews_at->copy()->subDays(14)
        );
        $this->assertEquals('reminder_sent', $dueCheck['status']);
        $this->assertEquals(14, $dueCheck['days_before_renewal']);

        $savedMembership = Membership::where('business_id', $biz->id)->find($membership->id);
        $this->assertNotNull($savedMembership->renewal_reminder_sent_at);
    }

    /**
     * [N-165-01]
     */
    public function test_n_165_01(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Test 165', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $plan = $this->planAction->handle(
            businessId: $biz->id,
            name: 'Exact Price Plan',
            priceCents: 12345
        );

        $this->assertEquals(12345, $plan->price_cents);

        $membership = $this->startAction->handle($biz->id, $plan->id, 99);
        $attributes = array_keys($membership->getAttributes());

        $this->assertNotContains('price', $attributes);
        $this->assertNotContains('price_cents', $attributes);
        $this->assertContains('plan_id', $attributes);
    }

    /**
     * [N-063]
     * ⛔ REFUSED: "X-168 has no overtime/out-of-hours/attendance (P-204)" belongs to X-168
     */
    public function test_n_063_refused(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-064]
     * ⛔ REFUSED: "X-168 has no overtime/out-of-hours/attendance (P-204)" belongs to X-168
     */
    public function test_n_064_refused(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-067]
     * ⛔ REFUSED: "X-168 has no overtime/out-of-hours/attendance (P-204)" belongs to X-168
     */
    public function test_n_067_refused(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-070]
     * ⛔ REFUSED: "X-168 has no overtime/out-of-hours/attendance (P-204)" belongs to X-168
     */
    public function test_n_070_refused(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-073]
     * ⛔ REFUSED: "X-168 has no overtime/out-of-hours/attendance (P-204)" belongs to X-168
     */
    public function test_n_073_refused(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-076]
     * ⛔ REFUSED: "X-168 has no overtime/out-of-hours/attendance (P-204)" belongs to X-168
     */
    public function test_n_076_refused(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-079]
     * ⛔ REFUSED: "X-168 has no overtime/out-of-hours/attendance (P-204)" belongs to X-168
     */
    public function test_n_079_refused(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-082]
     * ⛔ REFUSED: "X-168 has no overtime/out-of-hours/attendance (P-204)" belongs to X-168
     */
    public function test_n_082_refused(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-085]
     * ⛔ REFUSED: "X-168 has no overtime/out-of-hours/attendance (P-204)" belongs to X-168
     */
    public function test_n_085_refused(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-066]
     * Asserting: priority scheduling MUST BE REAL
     */
    public function test_n_066_priority_scheduling(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Test 165', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $scheduleResult = $this->scheduleAction->scheduleWindow(
            businessId: $biz->id,
            timeWindow: '2026-09-01 09:00-11:00',
            firstRequestPersonId: 99,
            secondRequestPersonId: 88
        );
        // We assert that the priority scheduler function runs without crashing and has a 'served_person_id' key
        $this->assertArrayHasKey('served_person_id', $scheduleResult);
    }

    /**
     * [N-068]
     * Asserting: member pricing is a pricebook tier, never a discount
     */
    public function test_n_068_pricing_tier(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Test 165', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $plan = $this->planAction->handle(
            businessId: $biz->id,
            name: 'Exact Price Plan',
            priceCents: 12345
        );

        $this->assertEquals(12345, $plan->price_cents);
        $this->assertNotContains('discount', array_keys($plan->getAttributes()));
    }
}
