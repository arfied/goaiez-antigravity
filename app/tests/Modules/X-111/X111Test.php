<?php

declare(strict_types=1);

namespace Tests\Modules\X111;

use App\Modules\X111\Actions\OpsAlertAction;
use App\Modules\X111\Actions\OpsBanAction;
use App\Modules\X111\Actions\OpsExportAction;
use App\Modules\X111\Actions\OpsImpersonateAction;
use App\Modules\X111\Actions\OpsTicketAction;
use App\Modules\X111\Domain\OpsEngine;
use App\Modules\X111\Events\AlertOperator;
use App\Modules\X111\Events\TicketOpened;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X111Test extends TestCase
{
    private OpsEngine $engine;

    private OpsAlertAction $alertAction;

    private OpsTicketAction $ticketAction;

    private OpsBanAction $banAction;

    private OpsExportAction $exportAction;

    private OpsImpersonateAction $impersonateAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new OpsEngine;
        $this->alertAction = new OpsAlertAction($this->engine);
        $this->ticketAction = new OpsTicketAction($this->engine);
        $this->banAction = new OpsBanAction($this->engine);
        $this->exportAction = new OpsExportAction;
        $this->impersonateAction = new OpsImpersonateAction;
    }

    /**
     * TEST ANCHOR
     * every alert row's message begins with a verb — asserted by a lint on the alert templates;
     * a tenant HUMAN request produces a ticket within one minute with the full transcript
     */
    public function test_anchor_action_verb_alert_message_and_human_request_ticket_generation(): void
    {
        Event::fake([AlertOperator::class, TicketOpened::class]);

        $biz = TestCase::provisionTenant(['name' => 'Ops Control Center Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Every alert row's message begins with an imperative verb (TEST ANCHOR, G21-05)
        $alert1 = $this->alertAction->handle($biz->id, 'critical', 'Investigate high error rate on Stripe webhook');
        $this->assertEquals($biz->id, $alert1->business_id);
        $this->assertEquals('critical', $alert1->severity);
        $this->assertEquals('open', $alert1->status);
        $this->assertMatchesRegularExpression('/^[A-Z][a-z]+ /', $alert1->action_verb_message);
        $firstWord1 = strtok($alert1->action_verb_message, ' ');
        $this->assertContains($firstWord1, ['Investigate', 'Review', 'Verify', 'Inspect', 'Escalate', 'Restart', 'Authorize', 'Audit', 'Halt', 'Resolve', 'Check']);

        // Test non-verb input gets prepended with an action verb automatically
        $alert2 = $this->alertAction->handle($biz->id, 'warning', 'database latency spike detected');
        $this->assertStringStartsWith('Review database latency spike detected', $alert2->action_verb_message);

        Event::assertDispatched(AlertOperator::class);

        // 2. Tenant HUMAN request produces a ticket within 1 minute with the full transcript (TEST ANCHOR)
        $transcriptText = "[10:00:01] User: Can I speak to a real person please?\n[10:00:05] Bot: Connecting you to support...";
        $before = Carbon::now();

        $ticket = $this->ticketAction->handle(
            businessId: $biz->id,
            fullTranscript: $transcriptText,
            category: 'human_escalation'
        );

        $this->assertEquals('human_requested', $ticket->source);
        $this->assertEquals($transcriptText, $ticket->full_transcript, 'Ticket preserves the full transcript');
        $this->assertTrue($ticket->created_at->diffInMinutes($before) <= 1, 'Ticket created within 1 minute of human request');
        $this->assertNotNull($ticket->sla_due_at);

        Event::assertDispatched(TicketOpened::class);

        // 3. [G17-07] IP Ban with TTL
        $ban = $this->banAction->handle($biz->id, '198.51.100.42', 'Fraud velocity spike', ttlHours: 48);
        $this->assertEquals('198.51.100.42', $ban->ip_address);
        $this->assertNotNull($ban->expires_at);
        $this->assertTrue($ban->expires_at->isFuture());
    }

    /**
     * [G1-29], [G1-35], [G2-63], [G4-05], [G4-14], [G4-24], [G4-28], [G4-31], [G4-33], [G4-36], [G4-40], [G4-41], [G4-47], [G5-17], [G5-18], [G5-38], [G7-33], [G9-05], [G9-19], [G9-20], [G9-28], [G17-07], [G17-17], [G19-09], [G21-02], [G21-05], [G21-14], [G15-28]
     */
    public function test_ops_console_capabilities(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }
}
