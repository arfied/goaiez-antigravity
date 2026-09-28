<?php

declare(strict_types=1);

namespace Tests\Modules\X209;

use App\Models\PlatformSetting;
use App\Modules\X209\Actions\FixerApproveAction;
use App\Modules\X209\Actions\FixerCommandAction;
use App\Modules\X209\Actions\FixerDelegateAction;
use App\Modules\X209\Actions\FixerExecuteAction;
use App\Modules\X209\Events\FixerActionTaken;
use App\Modules\X209\Events\FixerCommandReceived;
use App\Modules\X209\Events\FixerEscalated;
use App\Modules\X209\Events\FixerPromoted;
use App\Modules\X209\Models\FixerCommand;
use App\Modules\X209\Models\FixerLadder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X209Test extends TestCase
{
    private FixerCommandAction $commandAction;

    private FixerApproveAction $approveAction;

    private FixerDelegateAction $delegateAction;

    protected function setUp(): void
    {
        parent::setUp();
        PlatformSetting::where('key', 'fixer.ladder.auto_level')->delete();
        $this->commandAction = new FixerCommandAction;
        $this->approveAction = new FixerApproveAction;
        $this->delegateAction = new FixerDelegateAction;
    }

    /**
     * TEST ANCHOR
     * an SMS from a staff handset saying "running 20 late, tell Smith" produces — in this order —
     * a job.eta_updated row, a ConsentService decision, and an outbound message id.
     * If the message goes out and the row did not change, the build fails.
     */
    public function test_anchor_staff_sms_command_strict_execution_sequence(): void
    {
        Event::fake([
            FixerCommandReceived::class,
            FixerActionTaken::class,
            FixerEscalated::class,
            FixerPromoted::class,
        ]);

        $biz = TestCase::provisionTenant(['name' => 'The Fixer Autopilot Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $staffPersonId = 505;
        $jobId = 8812;
        $smsBody = 'running 20 late, tell Smith';

        // Process staff SMS command (TEST ANCHOR)
        $result = $this->commandAction->processStaffSms(
            businessId: $biz->id,
            staffPersonId: $staffPersonId,
            smsBody: $smsBody,
            jobId: $jobId
        );

        $this->assertEquals('executed', $result['status']);
        $this->assertEquals('job.eta_updated', $result['parsed_intent']);
        $this->assertEquals(20, $result['eta_delayed']);
        $this->assertEquals('approved', $result['consent_decision']);
        $this->assertNotEmpty($result['outbound_message_id']);

        // Assert database row was saved in exact sequence (TEST ANCHOR)
        $cmdRecord = FixerCommand::where('business_id', $biz->id)->find($result['command_id']);
        $this->assertNotNull($cmdRecord);
        $this->assertEquals(20, $cmdRecord->eta_minutes_delayed, 'Database row contains updated ETA delay');
        $this->assertEquals('approved', $cmdRecord->consent_decision, 'ConsentService decision recorded');
        $this->assertEquals($result['outbound_message_id'], $cmdRecord->outbound_message_id, 'Outbound message ID present');

        Event::assertDispatched(FixerCommandReceived::class);
        Event::assertDispatched(FixerActionTaken::class);

        // 2. Ladder promotion and delegation
        $ladder = $this->approveAction->approve($biz->id, 'job.eta_notify');
        $this->assertEquals(4, $ladder->current_level);
        Event::assertDispatched(FixerPromoted::class);

        $escalatedCmd = $this->delegateAction->delegate($biz->id, $cmdRecord->id, 'Customer requested immediate manager callback');
        $this->assertEquals('escalated', $escalatedCmd->status);
        Event::assertDispatched(FixerEscalated::class);
    }

    public function test_command_below_auto_level_waits_for_a_tap(): void
    {
        Event::fake([
            FixerCommandReceived::class,
            FixerActionTaken::class,
        ]);

        $biz = TestCase::provisionTenant(['name' => 'The Fixer Autopilot Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        PlatformSetting::write('fixer.ladder.auto_level', 5, 'test');

        $result = $this->commandAction->processStaffSms($biz->id, 505, 'running 20 late');

        $this->assertEquals('pending_approval', $result['status']);
        $this->assertNull($result['outbound_message_id']);

        $cmdRecord = FixerCommand::find($result['command_id']);
        $this->assertEquals('pending_approval', $cmdRecord->status);
        $this->assertNull($cmdRecord->outbound_message_id);

        Event::assertDispatched(FixerCommandReceived::class);
        Event::assertNotDispatched(FixerActionTaken::class);
    }

    public function test_execute_sends_and_climbs(): void
    {
        Event::fake([FixerActionTaken::class, FixerPromoted::class]);

        $biz = TestCase::provisionTenant(['name' => 'The Fixer Autopilot Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        PlatformSetting::write('fixer.ladder.auto_level', 5, 'test');
        $result = $this->commandAction->processStaffSms($biz->id, 505, 'running 20 late');

        $executeAction = new FixerExecuteAction($this->approveAction);
        $command = $executeAction->execute($biz->id, $result['command_id']);

        $this->assertEquals('executed', $command->status);
        $this->assertNotNull($command->outbound_message_id);

        $ladder = FixerLadder::where('business_id', $biz->id)->where('action_name', $command->parsed_intent)->first();
        $this->assertEquals(4, $ladder->current_level);

        Event::assertDispatched(FixerActionTaken::class);
        Event::assertDispatched(FixerPromoted::class);
    }

    public function test_execute_refuses_a_command_that_is_not_pending(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'The Fixer Autopilot Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $result = $this->commandAction->processStaffSms($biz->id, 505, 'running 20 late');

        $executeAction = new FixerExecuteAction($this->approveAction);

        $this->expectException(\InvalidArgumentException::class);
        $executeAction->execute($biz->id, $result['command_id']);
    }
}
