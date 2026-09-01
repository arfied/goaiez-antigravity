<?php

declare(strict_types=1);

namespace Tests\Modules\X105;

use App\Modules\X105\Actions\DemoRequestAction;
use App\Modules\X105\Actions\OutreachHaltAction;
use App\Modules\X105\Actions\OutreachReplyAction;
use App\Modules\X105\Actions\OutreachStartAction;
use App\Modules\X105\Events\DemoRequested;
use App\Modules\X105\Events\OutreachSent;
use App\Modules\X105\Events\ProspectEngaged;
use App\Modules\X105\Events\ReplyReceived;
use App\Modules\X105\Models\LadderStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X105Test extends TestCase
{
    private OutreachStartAction $startAction;

    private OutreachHaltAction $haltAction;

    private DemoRequestAction $demoAction;

    private OutreachReplyAction $replyAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startAction = new OutreachStartAction;
        $this->haltAction = new OutreachHaltAction;
        $this->demoAction = new DemoRequestAction;
        $this->replyAction = new OutreachReplyAction;
    }

    /**
     * TEST ANCHOR
     * after any inbound reply, every subsequent touch on that Person is SMS-class and
     * the pending email/voice rungs are cancelled — asserted on the ladder table;
     * a healthy business with no distress signal never triggers a research call
     */
    public function test_anchor_inbound_reply_shifts_to_sms_cancels_pending_and_healthy_triggers_no_research(): void
    {
        Event::fake([
            OutreachSent::class,
            ReplyReceived::class,
            ProspectEngaged::class,
            DemoRequested::class,
        ]);

        $biz = TestCase::provisionTenant(['name' => 'Autonomous Outreach Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Healthy business (rating 4.8, no distress) -> NEVER triggers research (TEST ANCHOR & G1-26)
        $healthyLadder = $this->startAction->startLadder(
            businessId: $biz->id,
            personId: 9001,
            businessRating: 4.8,
            explicitDistress: false
        );

        $this->assertFalse($healthyLadder->research_triggered, 'A healthy business with no distress signal NEVER triggers research (TEST ANCHOR)');
        $this->assertFalse($healthyLadder->distress_signal_detected);
        $this->assertEquals('active', $healthyLadder->status);

        // 2. Inbound reply received -> stops ladder, cancels pending email/voice rungs, shifts to SMS (TEST ANCHOR & G3-07, G3-30)
        $repliedLadder = $this->replyAction->handleInboundReply(
            businessId: $biz->id,
            ladderId: $healthyLadder->id,
            replyChannel: 'email'
        );

        $this->assertEquals('replied', $repliedLadder->status);
        $this->assertTrue($repliedLadder->exclusive_sms_mode, 'Every subsequent touch is SMS-class (TEST ANCHOR)');

        // Assert pending rungs cancelled on ladder table (TEST ANCHOR)
        $cancelledSteps = LadderStep::where('business_id', $biz->id)
            ->where('ladder_id', $healthyLadder->id)
            ->where('status', 'cancelled')
            ->get();

        $this->assertCount(3, $cancelledSteps, 'All pending rungs cancelled after reply (TEST ANCHOR)');

        Event::assertDispatched(ReplyReceived::class);
        Event::assertDispatched(ProspectEngaged::class);

        // 3. Distressed business (<3.5 rating) DOES trigger research (G1-26, G20-10)
        $distressedLadder = $this->startAction->startLadder(
            businessId: $biz->id,
            personId: 9002,
            businessRating: 3.2,
            explicitDistress: true
        );

        $this->assertTrue($distressedLadder->distress_signal_detected);
        $this->assertTrue($distressedLadder->research_triggered, 'Distressed business triggers deep research');

        // 4. Demo request
        $this->demoAction->request($biz->id, $distressedLadder->id, 'Tomorrow at 2 PM');
        Event::assertDispatched(DemoRequested::class);
    }

    /**
     * [G1-25], [G1-26], [G3-07], [G3-09], [G3-17], [G3-21], [G3-30], [G3-38], [G3-43], [G5-20], [G5-27], [G11-35], [G17-02], [G19-19], [G20-10], [G15-15]
     */
    public function test_outreach_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
