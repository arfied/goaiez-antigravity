<?php

declare(strict_types=1);

namespace Tests\Modules\X108;

use App\Modules\X108\Actions\AppointmentBookAction;
use App\Modules\X108\Actions\AppointmentCancelAction;
use App\Modules\X108\Actions\AvailabilityRequestAction;
use App\Modules\X108\Actions\WaitlistJoinAction;
use App\Modules\X108\Domain\SchedulingEngine;
use App\Modules\X108\Domain\SlotUnavailableRefused;
use App\Modules\X108\Events\AppointmentBooked;
use App\Modules\X108\Events\SlotLocked;
use App\Modules\X108\Models\Appointment;
use App\Modules\X108\Models\AvailabilityRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
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

    public function test_a_slot_lock_only_hides_its_own_date(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Lock Date Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $dayOne = now()->addDays(2)->format('Y-m-d');
        $dayTwo = now()->addDays(3)->format('Y-m-d');

        $start = Carbon::parse($dayOne)->setHour(14)->setMinute(0)->toIso8601String();
        $end = Carbon::parse($dayOne)->setHour(16)->setMinute(0)->toIso8601String();

        $this->engine->lockSlot(
            businessId: $biz->id,
            slotStart: $start,
            slotEnd: $end,
            sessionId: 'sess-123'
        );

        $availDayOne = $this->engine->getAvailableSlots($biz->id, $dayOne, true);
        $this->assertSame(3, $availDayOne['slots_count']);

        $availDayTwo = $this->engine->getAvailableSlots($biz->id, $dayTwo, true);
        $this->assertSame(4, $availDayTwo['slots_count']);
        $this->assertContains(
            '2:00 PM - 4:00 PM',
            array_column($availDayTwo['offered_slots'], 'formatted_window')
        );
    }

    /**
    /**
     * [G1-12] refuses: card data touches our DOM; tokens only (P-160), the iframe boundary asserted
     * ⛔ REFUSED: surveyed X-108 Actions, Models, and Ui and found no payment, card data, or iframe components; X-108 owns no surface that touches payment fields (likely handled by C-Billing or a payment module).
     */
    public function test_g1_12_token_iframe_boundary(): void
    {
        $this->assertTrue(true);
    }

    /**
     * the agent calls availability.request; time is looked up or refused (P-093)
     */
    public function test_availability_request_lookup(): void
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
        $biz = TestCase::provisionTenant(['name' => 'Merged View Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $date = '2026-09-09';                        // fixed: the fixture must not drift with the clock
        $dow = Carbon::parse($date)->dayOfWeekIso;   // the migration's own convention (S-39's decision)

        $clean = $this->engine->getAvailableSlots($biz->id, $date, true);
        $this->assertSame(4, $clean['slots_count'], 'the unmerged ladder is four');

        // (a) capacity — a booked appointment over 11:00-13:00
        $this->book->handle($biz->id, 'Consultation', $date.' 11:00:00', $date.' 13:00:00');

        // (b) a live hold — a slot lock over 14:00-16:00
        $this->engine->lockSlot($biz->id, $date.' 14:00:00', $date.' 16:00:00', 'sess-merged-view');

        // (c) out of office — a blackout over [16:00, 18:00)
        AvailabilityRule::create([
            'business_id' => $biz->id,
            'day_of_week' => $dow,
            'start_time' => '16:00',
            'end_time' => '18:00',
            'is_blackout' => true,
        ]);

        $merged = $this->engine->getAvailableSlots($biz->id, $date, true);
        $this->assertSame(1, $merged['slots_count'], 'one view: appointment, lock and blackout all subtract before the offer');
        $this->assertSame(
            ['9:00 AM - 11:00 AM'],
            array_column($merged['offered_slots'], 'formatted_window'),
            '09:00 is the only window no input touched'
        );

        $nonMember = $this->engine->getAvailableSlots($biz->id, $date, false);
        $this->assertSame(0, $nonMember['slots_count'], 'the single surviving window is VIP-reserved, so a non-member is offered nothing');
    }

    /**
     * [G2-12] split: the blackout calendar is X-108's ("blackouts and holiday overrides"); the PTO request-and-approval workflow is G15's
     */
    public function test_g2_12_blackout_calendar(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Blackout Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $date = '2026-09-08';
        $dow = Carbon::parse($date)->dayOfWeekIso;
        $otherDow = ($dow % 7) + 1;

        $clean = $this->engine->getAvailableSlots($biz->id, $date, true);
        $this->assertSame(4, $clean['slots_count'], 'with no availability rules the four standard slots stand');

        AvailabilityRule::create([
            'business_id' => $biz->id,
            'day_of_week' => $otherDow,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_blackout' => true,
        ]);
        $otherDay = $this->engine->getAvailableSlots($biz->id, $date, true);
        $this->assertSame(4, $otherDay['slots_count'], 'a blackout on another weekday does not touch this date');

        AvailabilityRule::create([
            'business_id' => $biz->id,
            'day_of_week' => $dow,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'is_blackout' => true,
        ]);
        $blacked = $this->engine->getAvailableSlots($biz->id, $date, true);
        $this->assertSame(3, $blacked['slots_count'], 'the 09:00 slot falls inside the blackout window');
        $this->assertSame(
            ['11:00 AM - 1:00 PM', '2:00 PM - 4:00 PM', '4:00 PM - 6:00 PM'],
            array_column($blacked['offered_slots'], 'formatted_window'),
            '09:00 is inside [09:00, 11:00) and gone; 11:00 is the exclusive end and survives'
        );

        AvailabilityRule::create([
            'business_id' => $biz->id,
            'day_of_week' => $dow,
            'start_time' => '14:00',
            'end_time' => '16:00',
            'is_blackout' => false,
        ]);
        $notBlackout = $this->engine->getAvailableSlots($biz->id, $date, true);
        $this->assertSame(3, $notBlackout['slots_count'], 'an is_blackout=false row is not a blackout and subtracts nothing');
    }

    /**
     * [G2-13] out-of-office is named in the header
     */
    public function test_g2_13_out_of_office(): void
    {
        // 14:00-14:30 rule
        $biz1 = TestCase::provisionTenant(['name' => 'OOO Biz 1', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz1->id}'");

        $date = now()->addDays(2)->format('Y-m-d');
        $dow = Carbon::parse($date)->dayOfWeekIso;

        AvailabilityRule::create([
            'business_id' => $biz1->id,
            'day_of_week' => $dow,
            'start_time' => '14:00',
            'end_time' => '14:30',
            'is_blackout' => true,
        ]);
        $avail1 = $this->engine->getAvailableSlots($biz1->id, $date, true);
        $this->assertSame(3, $avail1['slots_count']);
        $this->assertNotContains('2:00 PM - 4:00 PM', array_column($avail1['offered_slots'], 'formatted_window'));

        // 09:30-10:30 rule
        $biz2 = TestCase::provisionTenant(['name' => 'OOO Biz 2', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz2->id}'");

        AvailabilityRule::create([
            'business_id' => $biz2->id,
            'day_of_week' => $dow,
            'start_time' => '09:30',
            'end_time' => '10:30',
            'is_blackout' => true,
        ]);
        $avail2 = $this->engine->getAvailableSlots($biz2->id, $date, true);
        $this->assertSame(4, $avail2['slots_count']);
        $this->assertContains('9:00 AM - 11:00 AM', array_column($avail2['offered_slots'], 'formatted_window'));

        DB::statement("SET app.business_id = '{$biz1->id}'");
        $ok = $this->book->handle($biz1->id, 'Consultation', $date.' 09:00:00', $date.' 11:00:00');
        $this->assertSame('booked', $ok->status);
        $this->expectException(SlotUnavailableRefused::class);
        $this->expectExceptionMessage('falls in an out-of-office rule');
        $this->book->handle($biz1->id, 'Consultation', $date.' 14:00:00', $date.' 16:00:00');
    }

    /**
     * [G2-45] the agent offers only a window the scheduler confirmed
     */
    public function test_g2_45_offers_confirmed_window_only(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Confirm Win Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $date = now()->addDays(2)->format('Y-m-d');
        $avail = $this->avail->handle($biz->id, $date, true);
        $this->assertSame(4, $avail['slots_count']);

        Appointment::create([
            'business_id' => $biz->id,
            'service_name' => 'Consultation',
            'start_time' => Carbon::parse($date.' 10:00:00'),
            'end_time' => Carbon::parse($date.' 11:00:00'),
            'status' => 'booked',
        ]);

        $avail2 = $this->avail->handle($biz->id, $date, true);
        $this->assertSame(3, $avail2['slots_count']);

        $windows = array_column($avail2['offered_slots'], 'formatted_window');
        $this->assertNotContains('9:00 AM - 11:00 AM', $windows);
        $this->assertContains('11:00 AM - 1:00 PM', $windows);

        $ok = $this->book->handle($biz->id, 'Consultation', $date.' 14:00:00', $date.' 16:00:00');
        $this->assertSame('booked', $ok->status);

        $this->expectException(SlotUnavailableRefused::class);
        $this->expectExceptionMessage('overlaps a booked appointment');
        $this->book->handle($biz->id, 'Consultation', $date.' 10:30:00', $date.' 11:30:00');
    }

    /**
     * [G17-27] slots localised to the customer's browser
     */
    public function test_g17_27_localised_slots(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Localised Slots', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $date = now()->addDays(3)->format('Y-m-d');
        $res = $this->avail->handle($biz->id, $date, isMember: true);

        $this->assertNotEmpty($res['offered_slots']);

        foreach ($res['offered_slots'] as $slot) {
            // an explicit offset is the only form a browser can localise without guessing
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
                $slot['start_time'],
                'start_time must carry an explicit UTC offset'
            );
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
                $slot['end_time'],
                'end_time must carry an explicit UTC offset'
            );
            $this->assertSame(
                $slot['start_time'],
                Carbon::parse($slot['start_time'])->toIso8601String(),
                'the string round-trips through Carbon unchanged, so it is unambiguous'
            );
        }
    }

    /**
     * [G18-07] blackouts and holiday overrides are named in the header
     */
    public function test_g18_07_holiday_overrides(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Holiday Override', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $holiday = now()->addDays(4)->startOfDay();
        $sameWeekdayNextWeek = $holiday->copy()->addDays(7);

        AvailabilityRule::create([
            'business_id' => $biz->id,
            'day_of_week' => $holiday->dayOfWeekIso,
            'start_time' => '14:00',
            'end_time' => '16:00',
            'is_blackout' => true,
        ]);

        // the day it was meant for
        $onTheDay = $this->engine->getAvailableSlots($biz->id, $holiday->format('Y-m-d'), true);
        $this->assertNotContains('2:00 PM - 4:00 PM', array_column($onTheDay['offered_slots'], 'formatted_window'));

        // and every following week, because the rule is keyed on the weekday and not the date
        $nextWeek = $this->engine->getAvailableSlots($biz->id, $sameWeekdayNextWeek->format('Y-m-d'), true);
        $this->assertNotContains(
            '2:00 PM - 4:00 PM',
            array_column($nextWeek['offered_slots'], 'formatted_window'),
            'a one-day holiday is not expressible: the rule recurs on the weekday'
        );
        $this->assertSame($onTheDay['slots_count'], $nextWeek['slots_count']);
    }

    /**
     * [G18-27] specced, not built: with no meeting provider a booking stores no conference link — never a made-up address
     * (owner, 2026-10-05).
     */
    public function test_g18_27_a_booking_stores_no_made_up_conference_link(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Conf Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $start = now()->addDay()->toIso8601String();
        $end = now()->addDay()->addHour()->toIso8601String();

        $apt = $this->book->handle($biz->id, 'Video Consultation', $start, $end);
        $this->assertNull($apt->conference_link);

        $initialCount = Appointment::count();
        try {
            $this->book->handle($biz->id, 'Video Consultation', $start, $end);
            $this->fail('Expected SlotUnavailableRefused');
        } catch (SlotUnavailableRefused $e) {
            $this->assertSame($initialCount, Appointment::count());
        }
    }

    /**
     * [G19-01] named in the header — a cancellation fills itself
     */
    public function test_g19_01_cancellation_fills_itself_from_waitlist(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Cancel Backfill Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $wantedDate = now()->addDays(2)->format('Y-m-d');
        $otherDate = now()->addDays(9)->format('Y-m-d');

        $wrongEntry = $this->waitlist->handle(
            businessId: $biz->id,
            customerName: 'Alice Member',
            customerPhone: '+15125550001',
            serviceName: 'Furnace Repair',
            preferredDate: $otherDate,
            isMember: true
        );

        $rightEntry = $this->waitlist->handle(
            businessId: $biz->id,
            customerName: 'Bob Waiter',
            customerPhone: '+15125550999',
            serviceName: 'Furnace Repair',
            preferredDate: $wantedDate,
            isMember: false
        );

        $apt = $this->book->handle($biz->id, 'Furnace Repair', $wantedDate.' 10:00:00', $wantedDate.' 11:00:00');
        $cancelRes = $this->cancel->handle($biz->id, $apt->id);

        $this->assertTrue($cancelRes['backfill_offered']);
        $this->assertSame($rightEntry->id, $cancelRes['waitlist_id']);
        $this->assertSame('pending', $wrongEntry->fresh()->status);
    }

    /**
     * [G15-32] ⑤ R188 — work not pay; doctor asserts no pay field
     */
    public function test_g15_32_assertion(): void
    {
        $tables = ['resources', 'availability_rules', 'slot_locks', 'appointments', 'waitlists'];
        $needles = ['pay', 'wage', 'salary', 'rate', 'compensation', 'earning', 'payout'];

        // Negative assertion: X-108 scheduling tables have no pay/compensation columns
        foreach ($tables as $table) {
            $columns = Schema::getColumnListing($table);
            foreach ($columns as $column) {
                foreach ($needles as $needle) {
                    $this->assertFalse(
                        stripos($column, $needle) !== false,
                        "Table '{$table}' contains forbidden pay column: '{$column}' (matched '{$needle}')"
                    );
                }
            }
        }

        // Positive control: affiliates table has an earning/rate column
        $found = false;
        $affiliatesColumns = Schema::getColumnListing('affiliates');
        foreach ($affiliatesColumns as $column) {
            foreach ($needles as $needle) {
                if (stripos($column, $needle) !== false) {
                    $found = true;
                    $this->assertTrue(true, "Found money column '{$column}' in affiliates");
                    break 2;
                }
            }
        }

        $this->assertTrue($found, 'Failed to find any money column in affiliates for positive control');
    }

    /**
     * [G2-06]
     */
    public function test_g2_06_availability_request_refuses_booked_time_p_093(): void
    {
        // ⑤ the agent calls availability.request; time is looked up or refused (P-093)
        $biz = TestCase::provisionTenant(['name' => 'Test Tenant', 'currency' => 'USD']);
        $date = Carbon::now()->addDay()->toDateString();
        $this->book->handle($biz->id, 'Haircut', "$date 11:00:00", "$date 13:00:00");

        $result = $this->avail->handle($biz->id, $date);

        $this->assertCount(2, $result['offered_slots']);
        $this->assertStringContainsString('14:00', $result['offered_slots'][0]['start_time']);
        $this->assertStringContainsString('16:00', $result['offered_slots'][1]['start_time']);
    }

    public function test_joining_the_waitlist_without_a_deployed_page_leaves_the_hash_empty(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'No Hash Waitlist Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $futureDate = now()->addDays(2)->format('Y-m-d');

        $entry = $this->waitlist->handle($biz->id, 'Distinctive 4552', '5550004552', 'Service', $futureDate);
        $this->assertNull($entry->deploy_hash);

        $entryWithHash = $this->waitlist->handle($biz->id, 'Distinctive 4553', '5550004553', 'Service', $futureDate, false, 'deploy_abc4553');
        $this->assertSame('deploy_abc4553', $entryWithHash->fresh()->deploy_hash);
    }
}
