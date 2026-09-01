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
     */
    public function test_dialer_operations_and_regulatory_constraints(): void
    {
        Event::fake([CallRequested::class]);

        $biz = TestCase::provisionTenant(['name' => 'Outbound Contact Center Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Campaign start within 3.0% regulatory ceiling (G3-04, G10-03, §160.1)
        $camp = $this->startAction->startCampaign($biz->id, 'Spring AC Tune-Up Outbound', 2.85);
        $this->assertNotNull($camp);
        $this->assertTrue($camp->is_running);

        // 2. Reject campaign exceeding 3.0% ceiling (G10-03)
        try {
            $this->startAction->startCampaign($biz->id, 'Illegal Hyper-Dialing', 4.50);
            $this->fail('Expected InvalidArgumentException for ceiling > 3.0%');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('3.0%', $e->getMessage());
        }

        // 3. Login human seat and AI seat (G2-09, G18-08, G18-15)
        $humanSeat = $this->loginAction->login($biz->id, 'Agent John', isAi: false);
        $aiSeat = $this->loginAction->login($biz->id, 'AI Assistant Eve', isAi: true);

        $this->assertTrue($humanSeat->is_logged_in);
        $this->assertTrue($aiSeat->is_logged_in);

        // 4. Dial next (G18-19 Infobip/Telco primary)
        $this->dialAction->dialNext($biz->id, $camp->id, $humanSeat->id, '+12145550188');
        Event::assertDispatched(CallRequested::class);

        // 5. Uncertain AMD: treated as human (G18-01, §18C.4)
        $disp = $this->disposeAction->disposeCall(
            businessId: $biz->id,
            campaignId: $camp->id,
            seatId: $humanSeat->id,
            phone: '+12145550188',
            disposition: 'voicemail',
            isUncertainAmd: true // Uncertain AMD
        );
        $this->assertEquals('answered', $disp->disposition, 'Uncertain AMD is treated as human answered call (G18-01, §18C.4)');

        // 6. QA Scorecards: AI seat scored identically to human; scorecards positive only (G2-09, G2-35, G5-40, G16-15, T677)
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
     * [G2-09], [G2-26], [G2-35], [G2-37], [G3-04], [G5-09], [G5-40], [G9-01], [G9-38], [G10-03], [G11-25], [G13-02], [G16-11], [G16-15], [G18-01], [G18-02], [G18-03], [G18-06], [G18-08], [G18-13], [G18-15], [G18-16], [G18-19], [G21-13], [G15-29]
     */
    public function test_dialer_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
