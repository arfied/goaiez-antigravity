<?php

declare(strict_types=1);

namespace Tests\Modules\X200;

use App\Enums\UserRole;
use App\Models\User;
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
use App\Modules\X200\Ui\Wallboard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Livewire\Livewire;
use Symfony\Component\Process\Process;
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

    /**
     * [G18-13]
     */
    public function test_g18_13_qa_scorecard_is_positive_only(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Outbound Contact Center Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $seat = $this->loginAction->login($biz->id, 'Agent John', isAi: false);

        $qa = $this->qaAction->scoreCall($biz->id, $seat->id, 2003, 50, 'Some negative note');
        $this->assertTrue($qa->is_positive_only);
    }

    /**
     * [G3-04]
     */
    public function test_g3_04_predictive_pacing_under_the_3_percent_abandonment_ceiling(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Outbound Contact Center Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $camp = $this->startAction->startCampaign($biz->id, 'Spring AC Tune-Up Outbound', 3.00);
        $this->assertEquals(3.00, $camp->abandonment_ceiling_pct);

        $this->expectException(InvalidArgumentException::class);
        $this->startAction->startCampaign($biz->id, 'Illegal Hyper-Dialing', 3.01);
    }

    /**
     * Achievement layer has no rank or penalty columns.
     * [G2-35]
     * [G2-26]
     * [G2-37]
     * [G18-02]
     * [G18-13]
     * [G18-16]
     */
    public function test_g2_35_the_wallboard_scorecard_is_positive_only(): void
    {
        foreach (['rank', 'ranking', 'position', 'penalty', 'demerit'] as $col) {
            $this->assertFalse(Schema::hasColumn('qa_scorecards', $col), "qa_scorecards must not have $col");
        }
    }

    /**
     * Wallboard layer has no per-person tile/rank columns.
     * [G16-15]
     * [G9-38]
     * [G13-02]
     */
    public function test_g16_15_the_wallboard_positive_by_construction(): void
    {
        foreach (['rank', 'ranking', 'leaderboard_rank', 'position', 'penalty', 'demerit'] as $col) {
            $this->assertFalse(Schema::hasColumn('dialer_seats', $col), "dialer_seats must not have $col");
        }
    }

    /**
     * No per-person negative output exists in the schema.
     * [G15-29]
     */
    public function test_g15_29_no_per_person_negative_output_exists_in_the_schema(): void
    {
        foreach (['rank', 'ranking', 'leaderboard_rank', 'position', 'penalty', 'demerit'] as $col) {
            $this->assertFalse(Schema::hasColumn('qa_scorecards', $col), "qa_scorecards must not have $col");
        }
    }

    /**
     * [G18-19]
     */
    public function test_g18_19_twilio_is_absent(): void
    {
        Event::fake([CallRequested::class]);

        $biz = TestCase::provisionTenant(['name' => 'G18-19 Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $camp = $this->startAction->startCampaign($biz->id, 'G18-19 Campaign', 2.85);
        $humanSeat = $this->loginAction->login($biz->id, 'G18-19 Agent', isAi: false);

        $this->dialAction->dialNext($biz->id, $camp->id, $humanSeat->id, '+12145550188');
        Event::assertDispatched(CallRequested::class);

        $process = new Process(['grep', '-ri', 'twilio', app_path('Modules/X-200')]);
        $process->run();

        $output = $process->getOutput();
        $lines = explode("\n", trim($output));
        $offending = array_filter($lines, function ($line) {
            if ($line === '') {
                return false;
            }
            // Ignore the capabilities file where the rule is stated
            if (str_contains($line, 'capabilities.php')) {
                return false;
            }

            return true;
        });

        $this->assertEmpty($offending, 'Twilio is forbidden in the X-200 module (Infobip primary). Found: '.implode("\n", $offending));

        // Also assert CallRequested carries no provider
        $reflection = new \ReflectionClass(CallRequested::class);
        $this->assertFalse($reflection->hasProperty('provider'), 'CallRequested must not carry a provider');
    }

    /**
     * [G21-13]
     * The channel half of this rule is owned by X-01.
     */
    public function test_g21_13_a_closed_deal_appears_on_the_wallboard(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Wallboard Tenant', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $camp = $this->startAction->startCampaign($biz->id, 'Sales Camp', 3.00);
        $seat = $this->loginAction->login($biz->id, 'Agent Joe', isAi: false);

        $this->disposeAction->disposeCall(
            businessId: $biz->id,
            campaignId: $camp->id,
            seatId: $seat->id,
            phone: '+12145550188',
            disposition: 'sale_won',
            isUncertainAmd: false
        );

        $this->actingAs($owner);
        $this->get(route('x-200.wallboard'))->assertOk();

        $component = Livewire::test(Wallboard::class, ['businessId' => $biz->id]);
        $dispositions = $component->viewData('dispositions');
        $this->assertTrue($dispositions->contains('disposition', 'sale_won'), 'Wallboard must render the closed deal');
    }
}
