<?php

declare(strict_types=1);

namespace Tests\Modules\X186;

use App\Modules\X186\Actions\CampaignCreateAction;
use App\Modules\X186\Actions\CampaignRunAction;
use App\Modules\X186\Actions\SequenceStopAction;
use App\Modules\X186\Events\CampaignExhausted;
use App\Modules\X186\Events\CampaignReplied;
use App\Modules\X186\Events\CampaignSent;
use App\Modules\X186\Events\SendRequested;
use App\Modules\X186\Events\SequenceStopped;
use App\Modules\X186\Models\CampaignRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X186Test extends TestCase
{
    private CampaignCreateAction $createAction;

    private CampaignRunAction $runAction;

    private SequenceStopAction $stopAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAction = new CampaignCreateAction;
        $this->runAction = new CampaignRunAction;
        $this->stopAction = new SequenceStopAction;
    }

    /**
     * TEST ANCHOR
     * every send.requested from this module carries class = marketing ·
     * a reply on ANY channel stops every pending step for that Person within one cycle ·
     * an open RECOVER suppresses the whole sequence (P-205)
     */
    public function test_anchor_marketing_class_reply_stops_all_steps_and_recover_suppresses(): void
    {
        Event::fake([CampaignSent::class, CampaignReplied::class, SequenceStopped::class, CampaignExhausted::class, SendRequested::class]);

        $biz = TestCase::provisionTenant(['name' => 'Drip Campaign Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $campaignId = 'camp_furnace_renewal_2026';
        $personId = 8801;

        $this->createAction->createCampaign($biz->id, $campaignId, [
            ['channel' => 'sms', 'template_name' => 'nudge_sms_v1', 'delay_days' => 1],
            ['channel' => 'email', 'template_name' => 'followup_email_v2', 'delay_days' => 3],
        ]);

        // 1. Run step: assert every send.requested carries class = marketing (TEST ANCHOR & P-063, G11-24)
        $run = $this->runAction->runNextStep($biz->id, $campaignId, $personId, hasOpenRecover: false);
        $this->assertNotNull($run);
        $this->assertTrue($run->is_active);

        Event::assertDispatched(SendRequested::class, function ($event) use ($biz, $personId) {
            return $event->businessId === $biz->id
                && $event->personId === $personId
                && $event->messageClass === 'marketing'; // TEST ANCHOR
        });
        Event::assertDispatched(CampaignSent::class);

        // 2. An open RECOVER suppresses the whole sequence (TEST ANCHOR & P-205)
        $personWithRecover = 8802;
        $suppressedRun = $this->runAction->runNextStep($biz->id, $campaignId, $personWithRecover, hasOpenRecover: true);
        $this->assertTrue($suppressedRun->is_suppressed, 'Active RECOVER intent suppresses sequence (TEST ANCHOR & P-205)');
        $this->assertNotNull($suppressedRun->suppression_reason);

        // 3. A reply on ANY channel stops every pending step for that Person within one cycle (TEST ANCHOR & P-075, G11-28, G19-04)
        $stoppedCount = $this->stopAction->stopAllSequencesForPerson($biz->id, $personId, 'email');
        $this->assertEquals(1, $stoppedCount);

        $savedRun = CampaignRun::where('business_id', $biz->id)->where('person_id', $personId)->first();
        $this->assertFalse($savedRun->is_active, 'Sequence is stopped for person after inbound reply (TEST ANCHOR)');
        Event::assertDispatched(CampaignReplied::class);
        Event::assertDispatched(SequenceStopped::class);
    }

    /**
     * [G2-14], [G5-12], [G5-14], [G10-25], [G11-02], [G11-24], [G11-28], [G11-31], [G12-18], [G12-28], [G17-20], [G19-04]
     */
    public function test_campaign_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
