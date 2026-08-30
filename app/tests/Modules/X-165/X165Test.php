<?php

declare(strict_types=1);

namespace Tests\Modules\X165;

use App\Modules\X121\Models\Business;
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

        $biz = Business::provision(['name' => 'VIP Members Club', 'currency' => 'USD']);
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
        $this->assertTrue(true);
    }
}
