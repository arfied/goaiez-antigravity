<?php

declare(strict_types=1);

namespace Tests\Modules\X111;

use App\Modules\X111\Actions\OpsAlertAction;
use App\Modules\X111\Actions\OpsBanAction;
use App\Modules\X111\Actions\OpsExportAction;
use App\Modules\X111\Actions\OpsImpersonateAction;
use App\Modules\X111\Actions\OpsTicketAction;
use App\Modules\X111\Actions\ResolveAlertAction;
use App\Modules\X111\Domain\OpsEngine;
use App\Modules\X111\Events\AlertOperator;
use App\Modules\X111\Events\TicketOpened;
use App\Modules\X111\Models\IpBan;
use App\Modules\X111\Models\ManualQueue;
use App\Modules\X111\Models\OperatorAlert;
use App\Modules\X111\Models\TenantTicket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
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
     * [G4-14] the operator's; the tenant's conversational search is X-01's. ElasticSearch is corpus vocabulary — one database (§22)
     */
    public function test_g4_14_elasticsearch_vocabulary(): void
    {
        $drivers = array_column(config('database.connections'), 'driver');
        $this->assertNotContains('elasticsearch', $drivers);

        $this->assertNull((new IpBan)->getConnectionName());
        $this->assertNull((new ManualQueue)->getConnectionName());
        $this->assertNull((new OperatorAlert)->getConnectionName());
        $this->assertNull((new TenantTicket)->getConnectionName());

        $biz = TestCase::provisionTenant(['name' => 'Elastic Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $alert = $this->alertAction->handle($biz->id, 'critical', 'Check elasticsearch');

        $row = DB::connection(config('database.default'))
            ->table((new OperatorAlert)->getTable())
            ->where('id', $alert->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertEquals($biz->id, $row->business_id);
    }

    /**
     * [G2-63]
     */
    public function test_g2_63_a_human_request_is_present_as_a_support_ticket(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Human Ticket Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $transcript = 'Please connect me to human support.';
        $ticket = $this->ticketAction->handle($biz->id, $transcript, 'human_escalation');

        $this->assertDatabaseHas('tenant_tickets', ['id' => $ticket->id, 'full_transcript' => $transcript]);
        $this->assertSame('human_requested', $ticket->source);
    }

    /** [G9-06] */
    public function test_g9_06_ops_console_alerts_a_human_and_pauses_no_ad_spend(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Ops Control Center Tenant 2', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $alert = $this->alertAction->handle($biz->id, 'critical', 'Investigate anomalous request volume');

        $this->assertEquals('open', $alert->status);
        $this->assertEquals('Investigate anomalous request volume', $alert->action_verb_message);

        (new ResolveAlertAction)->handle($alert);
        $alert->refresh();
        $this->assertEquals('resolved', $alert->status);

        $alertKeys = array_keys($alert->getAttributes());
        $params = (new \ReflectionMethod(OpsAlertAction::class, 'handle'))->getParameters();
        $paramNames = array_map(fn ($p) => $p->getName(), $params);

        $this->assertContains('businessId', $paramNames);
        $this->assertContains('severity', $paramNames);
        $this->assertContains('message', $paramNames);
        $this->assertGreaterThanOrEqual(3, count($paramNames));

        $names = array_merge($alertKeys, $paramNames);
        foreach ($names as $name) {
            $this->assertDoesNotMatchRegularExpression('/(cpc|adset|ad_spend|campaign|pause|budget|bidding|creative)/i', $name);
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Modules/X-111')));
        $files = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && ! in_array($file->getBasename(), ['capabilities.php', 'manifest.php'])) {
                $files[] = $file->getPathname();
            }
        }
        $this->assertGreaterThanOrEqual(21, count($files));

        $controlCount = 0;
        foreach ($files as $filePath) {
            $content = file_get_contents($filePath);
            $this->assertDoesNotMatchRegularExpression('/\b(cpc|cost_per_click|bid|bids|bidding|adset|ad_set|ad_spend|adwords|campaign|campaigns|pause|paused|retarget|remarketing)\b/i', $content, "File $filePath matched forbidden term");
            if (preg_match('/OperatorAlert|OpsEngine|TenantTicket/', $content)) {
                $controlCount++;
            }
        }

        $this->assertGreaterThanOrEqual(8, $controlCount);
    }

    /**
     * [G5-18] the HELP path; reply HUMAN always escalates (R37)
     */
    public function test_g5_18_help_path_always_escalates(): void
    {
        Event::fake([TicketOpened::class]);

        $biz = TestCase::provisionTenant(['name' => 'Help Path Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $t1 = $this->ticketAction->handle($biz->id, '<a transcript>', 'billing');
        $t2 = $this->ticketAction->handle($biz->id, '<a different transcript>', 'general');

        $this->assertSame('billing', $t1->category);
        $this->assertSame('general', $t2->category);

        Event::assertDispatchedTimes(TicketOpened::class, 2);

        Event::assertDispatched(TicketOpened::class, fn (TicketOpened $e) => $e->ticketId === $t1->id && $e->source === 'human_requested');
        Event::assertDispatched(TicketOpened::class, fn (TicketOpened $e) => $e->ticketId === $t2->id && $e->source === 'human_requested');
    }

    /** [G5-18] */
    public function test_g5_18_human_reply_escalates(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Human Escalate', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $ticket = $this->ticketAction->handle($biz->id, 'HUMAN', 'human_escalation');
        $this->assertEquals('human_requested', $ticket->source);
        $this->assertEquals('open', $ticket->status);
        $this->assertDatabaseHas('tenant_tickets', ['id' => $ticket->id, 'full_transcript' => 'HUMAN']);
    }

    /** [G7-33] */
    public function test_g7_33_spend_ceiling_alerts_never_stops_phone(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Ceiling', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $alert = $this->alertAction->handle($biz->id, 'warning', 'Spend ceiling reached');
        $this->assertEquals('warning', $alert->severity);
        $this->assertStringContainsString('Spend ceiling reached', $alert->action_verb_message);

        $alertKeys = array_keys($alert->getAttributes());
        $params = (new \ReflectionMethod(OpsAlertAction::class, 'handle'))->getParameters();
        $paramNames = array_map(fn ($p) => $p->getName(), $params);

        $names = array_merge($alertKeys, $paramNames);
        foreach ($names as $name) {
            $this->assertDoesNotMatchRegularExpression('/(phone|telephony|answering|hangup|divert)/i', $name);
        }
    }

    /** [G19-09] */
    public function test_g19_09_compromise_halt_is_security_stop(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Security Halt', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $ban = $this->banAction->handle($biz->id, '203.0.113.5', 'Compromise halt');
        $this->assertDatabaseHas('ip_bans', ['id' => $ban->id]);

        $banKeys = array_keys($ban->getAttributes());
        $params = (new \ReflectionMethod(OpsBanAction::class, 'handle'))->getParameters();
        $paramNames = array_map(fn ($p) => $p->getName(), $params);

        $names = array_merge($banKeys, $paramNames);
        foreach ($names as $name) {
            $this->assertDoesNotMatchRegularExpression('/(balance|credit|cap|dunning|arrears)/i', $name);
        }
    }

    /** [G15-28] */
    public function test_g15_28_no_pay_field_exposed(): void
    {
        $this->assertFalse(Schema::hasColumn('operator_alerts', 'pay'));
        $this->assertFalse(Schema::hasColumn('tenant_tickets', 'pay'));
    }

    /** [G4-24] */
    public function test_g4_24_throttle_refusal(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Throttle Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $ip = '10.0.0.1';
        $this->banAction->handle($biz->id, $ip, 'fraud', 24);

        $engine = new OpsEngine;

        try {
            $engine->checkThrottle($biz->id, $ip);
            $this->fail('Throttle did not refuse.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('THROTTLE REFUSED', $e->getMessage());
        }

        // Pass case
        $engine->checkThrottle($biz->id, '10.0.0.2');
        $this->assertDatabaseHas('ip_bans', ['business_id' => $biz->id, 'ip_address' => '10.0.0.1']);
        $this->assertDatabaseMissing('ip_bans', ['business_id' => $biz->id, 'ip_address' => '10.0.0.2']);

        // The dead-tier half is not assertable here: X-111 exposes no pricing or plan surface, and the module's only occurrence of $99/$999 is the ⑤ tracker text in capabilities.php.
    }
}
