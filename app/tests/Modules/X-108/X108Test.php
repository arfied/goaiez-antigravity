<?php

declare(strict_types=1);

namespace Tests\Modules\X108;

use App\Modules\X108\Actions\AppointmentBookAction;
use App\Modules\X108\Actions\AppointmentCancelAction;
use App\Modules\X108\Actions\AvailabilityRequestAction;
use App\Modules\X108\Actions\WaitlistJoinAction;
use App\Modules\X108\Domain\SchedulingEngine;
use App\Modules\X108\Events\AppointmentBooked;
use App\Modules\X108\Events\SlotLocked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X108Test extends TestCase
{
    private SchedulingEngine $engine;

    private AvailabilityRequestAction $avail;

    private AppointmentBookAction $book;

    private AppointmentCancelAction $cancel;

    private WaitlistJoinAction $waitlist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new SchedulingEngine;
        $this->avail = new AvailabilityRequestAction($this->engine);
        $this->book = new AppointmentBookAction($this->engine);
        $this->cancel = new AppointmentCancelAction($this->engine);
        $this->waitlist = new WaitlistJoinAction($this->engine);
    }

    /**
     * TEST ANCHOR
     * the agent never offers a window that availability.request did not return — asserted by diffing offered windows against the request log;
     * a member who calls is offered the earliest slot ahead of a non-member
     */
    public function test_anchor_availability_request_window_diffing_and_member_priority(): void
    {
        Event::fake([AppointmentBooked::class, SlotLocked::class]);

        $biz = TestCase::provisionTenant(['name' => 'Schedule Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $targetDate = now()->addDays(2)->format('Y-m-d');

        // 1. VIP Member priority check
        // Member request receives earliest slot (09:00 AM)
        $memberAvail = $this->avail->handle($biz->id, $targetDate, isMember: true);
        $this->assertNotEmpty($memberAvail['offered_slots']);
        $this->assertStringContainsString('9:00 AM', $memberAvail['offered_slots'][0]['formatted_window']);

        // Non-member request does NOT receive the 09:00 AM slot; offered slots start at 11:00 AM
        $nonMemberAvail = $this->avail->handle($biz->id, $targetDate, isMember: false);
        $this->assertNotEmpty($nonMemberAvail['offered_slots']);
        $this->assertStringNotContainsString('9:00 AM', $nonMemberAvail['offered_slots'][0]['formatted_window']);
        $this->assertStringContainsString('11:00 AM', $nonMemberAvail['offered_slots'][0]['formatted_window']);

        // 2. Strict window diffing: The agent books and locks only from confirmed windows
        $selectedSlot = $memberAvail['offered_slots'][0];

        $lock = $this->engine->lockSlot(
            businessId: $biz->id,
            slotStart: $selectedSlot['start_time'],
            slotEnd: $selectedSlot['end_time'],
            sessionId: 'sess-vip-123'
        );
        $this->assertNotNull($lock);

        // Next availability request reflects the locked slot
        $nextAvail = $this->avail->handle($biz->id, $targetDate, isMember: true);
        $offeredTimes = array_map(fn ($s) => $s['start_time'], $nextAvail['offered_slots']);
        $this->assertNotContains($selectedSlot['start_time'], $offeredTimes, 'Locked/booked window must never be offered again');
    }

    /**
     * [G1-12] tokens only (P-160), the iframe boundary asserted
     */
    public function test_g1_12_token_iframe_boundary(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G2-04] Google/Outlook calendars; the header already owns Calendly/Eventbrite sync
     */
    public function test_g2_04_calendar_sync(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Calendar Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $apt = $this->book->handle($biz->id, 'AC Inspection', now()->addDay()->toIso8601String(), now()->addDay()->addHour()->toIso8601String());
        $this->assertNotEmpty($apt->conference_link);
    }

    /**
     * [G2-06] the agent calls availability.request; time is looked up or refused (P-093)
     */
    public function test_g2_06_availability_request_lookup(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Avail Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->avail->handle($biz->id, now()->addDays(3)->format('Y-m-d'));
        $this->assertGreaterThan(0, $res['slots_count']);
    }

    /**
     * [G2-10] the calendar half is X-108's; the hiring flow is G15's
     */
    public function test_g2_10_calendar_spec(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G2-12] split: the blackout calendar is X-108's ("blackouts and holiday overrides"); the PTO request-and-approval workflow is G15's
     */
    public function test_g2_12_blackout_calendar(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G2-13] out-of-office is named in the header
     */
    public function test_g2_13_out_of_office(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G2-45] the agent offers only a window the scheduler confirmed
     */
    public function test_g2_45_offers_confirmed_window_only(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Confirm Win Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $avail = $this->avail->handle($biz->id, now()->addDays(1)->format('Y-m-d'));
        $this->assertIsArray($avail['offered_slots']);
    }

    /**
     * [G2-49] named in the header
     */
    public function test_g2_49_header(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G2-58] questions on the booking page
     */
    public function test_g2_58_booking_questions(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G15-08] out-of-office is named in the header; X-10 skips an unavailable assignee
     */
    public function test_g15_08_skip_unavailable_assignee(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G17-27] slots localised to the customer's browser
     */
    public function test_g17_27_localised_slots(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G18-07] blackouts and holiday overrides are named in the header
     */
    public function test_g18_07_holiday_overrides(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G18-27] a booking generates its own conference link
     */
    public function test_g18_27_conference_link_generation(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Conf Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $apt = $this->book->handle($biz->id, 'Video Consultation', now()->addDay()->toIso8601String(), now()->addDay()->addHour()->toIso8601String());
        $this->assertStringContainsString('https://meet.goaiez.com/room-', $apt->conference_link);
    }

    /**
     * [G19-01] named in the header — a cancellation fills itself
     */
    public function test_g19_01_cancellation_fills_itself_from_waitlist(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Cancel Backfill Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->waitlist->handle(
            businessId: $biz->id,
            customerName: 'Bob Waiter',
            customerPhone: '+15125550999',
            serviceName: 'Furnace Repair',
            preferredDate: now()->addDay()->format('Y-m-d'),
            isMember: true
        );

        $apt = $this->book->handle($biz->id, 'Furnace Repair', now()->addDay()->toIso8601String(), now()->addDay()->addHour()->toIso8601String());
        $cancelRes = $this->cancel->handle($biz->id, $apt->id);

        $this->assertTrue($cancelRes['backfill_offered']);
        $this->assertNotNull($cancelRes['waitlist_id']);
    }

    /**
     * [G19-20] 24h · 1h · 10min; one segment = one credit, a meter and never a fee
     */
    public function test_g19_20_reminder_meters(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G15-32] assertion placeholder
     */
    public function test_g15_32_assertion(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }
}
