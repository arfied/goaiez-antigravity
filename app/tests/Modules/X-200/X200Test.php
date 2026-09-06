<?php

declare(strict_types=1);

namespace Tests\Modules\X200;

use App\Modules\X200\Actions\CallbackScheduleAction;
use App\Modules\X200\Actions\CallDisposeAction;
use App\Modules\X200\Actions\CampaignPauseAction;
use App\Modules\X200\Actions\CampaignStartAction;
use App\Modules\X200\Actions\DialNextAction;
use App\Modules\X200\Actions\QaScoreAction;
use App\Modules\X200\Actions\SeatLoginAction;
use App\Modules\X200\Actions\SeatLogoutAction;
use App\Modules\X200\Events\CallRequested;
use App\Modules\X200\Models\CallDisposition;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class X200Test extends TestCase
{
    private CampaignStartAction $startAction;

    private CampaignPauseAction $pauseAction;

    private SeatLoginAction $loginAction;

    private SeatLogoutAction $logoutAction;

    private DialNextAction $dialAction;

    private CallDisposeAction $disposeAction;

    private CallbackScheduleAction $scheduleAction;

    private QaScoreAction $qaAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startAction = new CampaignStartAction;
        $this->pauseAction = new CampaignPauseAction;
        $this->loginAction = new SeatLoginAction;
        $this->logoutAction = new SeatLogoutAction;
        $this->dialAction = new DialNextAction;
        $this->disposeAction = new CallDisposeAction;
        $this->scheduleAction = new CallbackScheduleAction;
        $this->qaAction = new QaScoreAction;
    }

    /**
     * Testing abandonment ceiling <= 3.0%, uncertain AMD treated as human, and AI seat scored like human.
     * [G10-03]
     * [G18-01]
     */
    public function test_dialer_operations_and_regulatory_constraints(): void
    {
        Event::fake([CallRequested::class]);

        $biz = TestCase::provisionTenant(['name' => 'Outbound Contact Center Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Campaign start within 3.0% regulatory ceiling (§160.1)
        $camp = $this->startAction->startCampaign($biz->id, 'Spring AC Tune-Up Outbound', 2.85);
        $this->assertNotNull($camp);
        $this->assertTrue($camp->is_running);

        // 2. Reject campaign exceeding 3.0% ceiling
        try {
            $this->startAction->startCampaign($biz->id, 'Illegal Hyper-Dialing', 4.50);
            $this->fail('Expected InvalidArgumentException for ceiling > 3.0%');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('3.0%', $e->getMessage());
        }

        // 3. Login human seat and AI seat
        $humanSeat = $this->loginAction->login($biz->id, 'Agent John', isAi: false);
        $aiSeat = $this->loginAction->login($biz->id, 'AI Assistant Eve', isAi: true);

        $this->assertTrue($humanSeat->is_logged_in);
        $this->assertTrue($aiSeat->is_logged_in);

        // 4. Dial next (Infobip/Telco primary)
        $this->dialAction->dialNext($biz->id, $camp->id, $humanSeat->id, '+12145550188');
        Event::assertDispatched(CallRequested::class);

        // 5. Uncertain AMD: treated as human (§18C.4)
        $disp = $this->disposeAction->disposeCall(
            businessId: $biz->id,
            campaignId: $camp->id,
            seatId: $humanSeat->id,
            phone: '+12145550188',
            disposition: 'voicemail',
            isUncertainAmd: true // Uncertain AMD
        );
        $this->assertEquals('answered', $disp->disposition, 'Uncertain AMD is treated as human answered call (G18-01, §18C.4)');

        // 6. QA Scorecards: AI seat scored identically to human; scorecards positive only (T677)
        $humanQa = $this->qaAction->scoreCall($biz->id, $humanSeat->id, 1001, 92, 'Great empathy shown');
        $aiQa = $this->qaAction->scoreCall($biz->id, $aiSeat->id, 1002, 98, 'Zero latency response');

        $this->assertEquals(92, $humanQa->qa_rating);
        $this->assertEquals(98, $aiQa->qa_rating);
        $this->assertTrue($humanQa->is_positive_only);
        $this->assertTrue($aiQa->is_positive_only);

        // 7. Schedule callback
        $callback = $this->scheduleAction->scheduleCallback($biz->id, '+12145550188', now()->addHours(2));
        $this->assertNotNull($callback);

        // 8. Logout seat and pause campaign
        $this->logoutAction->logout($biz->id, $humanSeat->id);
        $paused = $this->pauseAction->pauseCampaign($biz->id, $camp->id);
        $this->assertFalse($paused->is_running);
    }

    /**
     * [G18-25]
     */
    public function test_g18_25_certain_voicemail_is_not_upgraded_to_a_live_human(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Outbound Contact Center Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $camp = $this->startAction->startCampaign($biz->id, 'Spring AC Tune-Up Outbound', 2.85);
        $humanSeat = $this->loginAction->login($biz->id, 'Agent John', isAi: false);

        $disp = $this->disposeAction->disposeCall(
            businessId: $biz->id,
            campaignId: $camp->id,
            seatId: $humanSeat->id,
            phone: '+12145550188',
            disposition: 'voicemail',
            isUncertainAmd: false // Certain AMD
        );

        $this->assertEquals('voicemail', $disp->disposition);
        $this->assertFalse($disp->is_uncertain_human);
    }

    public function test_dispose_refuses_a_campaign_from_another_business(): void
    {
        $bizA = TestCase::provisionTenant(['name' => 'Outbound Contact Center Tenant', 'currency' => 'USD']);
        $bizB = TestCase::provisionTenant(['name' => 'Second Contact Center Tenant', 'currency' => 'USD']);

        DB::statement("SET app.business_id = '{$bizB->id}'");
        $foreignCamp = $this->startAction->startCampaign($bizB->id, 'Other Tenant Campaign', 2.50);
        $foreignSeat = $this->loginAction->login($bizB->id, 'Agent Mallory', isAi: false);

        DB::statement("SET app.business_id = '{$bizA->id}'");
        $camp = $this->startAction->startCampaign($bizA->id, 'Spring AC Tune-Up Outbound', 2.85);
        $seat = $this->loginAction->login($bizA->id, 'Agent John', isAi: false);

        try {
            $this->disposeAction->disposeCall(
                businessId: $bizA->id,
                campaignId: $foreignCamp->id,
                seatId: $seat->id,
                phone: '+12145550188',
                disposition: 'answered'
            );
            $this->fail('disposeCall accepted a campaign id belonging to another business');
        } catch (ModelNotFoundException) {
            // expected
        }

        $this->assertSame(
            0,
            CallDisposition::where('business_id', $bizA->id)->count(),
            'the disposition row must not be written before the ids are validated'
        );
    }

    public function test_dispose_refuses_a_seat_from_another_business(): void
    {
        $bizA = TestCase::provisionTenant(['name' => 'Outbound Contact Center Tenant', 'currency' => 'USD']);
        $bizB = TestCase::provisionTenant(['name' => 'Second Contact Center Tenant', 'currency' => 'USD']);

        DB::statement("SET app.business_id = '{$bizB->id}'");
        $foreignCamp = $this->startAction->startCampaign($bizB->id, 'Other Tenant Campaign', 2.50);
        $foreignSeat = $this->loginAction->login($bizB->id, 'Agent Mallory', isAi: false);

        DB::statement("SET app.business_id = '{$bizA->id}'");
        $camp = $this->startAction->startCampaign($bizA->id, 'Spring AC Tune-Up Outbound', 2.85);
        $seat = $this->loginAction->login($bizA->id, 'Agent John', isAi: false);

        try {
            $this->disposeAction->disposeCall(
                businessId: $bizA->id,
                campaignId: $camp->id,
                seatId: $foreignSeat->id,
                phone: '+12145550188',
                disposition: 'answered'
            );
            $this->fail('disposeCall accepted a seat id belonging to another business');
        } catch (ModelNotFoundException) {
            // expected
        }

        $this->assertSame(
            0,
            CallDisposition::where('business_id', $bizA->id)->count(),
            'the disposition row must not be written before the ids are validated'
        );
    }

    public function test_a_paused_campaign_does_not_dial(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Outbound Contact Center Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        Event::fake([CallRequested::class]);

        $camp = $this->startAction->startCampaign($biz->id, 'Spring AC Tune-Up Outbound', 2.85);
        $seat = $this->loginAction->login($biz->id, 'Agent John', isAi: false);

        $this->pauseAction->pauseCampaign($biz->id, $camp->id);

        try {
            $this->dialAction->dialNext($biz->id, $camp->id, $seat->id, '+12145550188');
            $this->fail('dialNext dialed a paused campaign');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('campaign is paused', $e->getMessage());
        }

        Event::assertNotDispatched(CallRequested::class);

        $seat->refresh();
        $this->assertSame('idle', $seat->state, 'a refused dial must not leave the seat in dialing');
    }

    /**
     * [G2-09]
     */
    public function test_g2_09_an_ai_seat_is_scored_exactly_like_a_human(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Outbound Contact Center Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $humanSeat = $this->loginAction->login($biz->id, 'Agent John', isAi: false);
        $aiSeat = $this->loginAction->login($biz->id, 'AI Assistant Eve', isAi: true);

        $humanQa = $this->qaAction->scoreCall($biz->id, $humanSeat->id, 1001, 90);
        $aiQa = $this->qaAction->scoreCall($biz->id, $aiSeat->id, 1002, 90);

        $this->assertSame($humanQa->qa_rating, $aiQa->qa_rating, 'an AI seat is scored exactly like a human');
        $this->assertSame(90, $aiQa->qa_rating);
    }

    /**
     * [G5-40]
     */
    public function test_g5_40_the_qa_scorecard_clamps_a_rating_to_the_0_100_range(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Outbound Contact Center Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $seat = $this->loginAction->login($biz->id, 'Agent John', isAi: false);

        $this->assertSame(100, $this->qaAction->scoreCall($biz->id, $seat->id, 2001, 150)->qa_rating);
        $this->assertSame(0, $this->qaAction->scoreCall($biz->id, $seat->id, 2002, -5)->qa_rating);
    }
}
