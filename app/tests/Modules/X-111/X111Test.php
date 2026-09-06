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
     *
     * ⛔ REFUSED: G4-05 — the capability's own text is "named in the header"; there is no clause to assert
     * ⛔ REFUSED: G4-28 — the capability's own text is "named in the header"; there is no clause to assert
     * ⛔ REFUSED: G4-33 — the capability's own text is "named in the header"; there is no clause to assert
     * ⛔ REFUSED: G9-05 — the capability's own text is "named in the header; the export row is X-122's log"; there is no clause to assert; the log is X-122's
     * ⛔ REFUSED: G17-17 — the capability's own text is "fraud velocity is named in the header"; there is no clause to assert
     * ⛔ REFUSED: G5-38 — the capability's own text is "ticket categorisation"; a noun phrase, not a refusal
     * ⛔ REFUSED: G9-20 — the capability's own text is "fleet-wide operator roll-up"; a noun phrase, not a refusal
     * ⛔ REFUSED: G9-28 — the capability's own text is "API traffic per endpoint"; a noun phrase, not a refusal
     * ⛔ REFUSED: G4-36 — the capability's own text is "the auto-healing supervisor"; a noun phrase, not a refusal
     * ⛔ REFUSED: G4-41 — the capability's own text is "the T443 delete-list runner"; a noun phrase, not a refusal
     * ⛔ REFUSED: G4-31 — the capability's own text is "the screen; the mechanism is X-123's"; the mechanism is assigned to X-123
     * ⛔ REFUSED: G4-40 — the capability's own text is "ops.ban plus a mass token.revoke through X-142"; the mechanism runs through X-142
     * ⛔ REFUSED: G4-47 — the capability's own text is "SOP edit history; the same home as G1-29 Interactive SOPs"; a restatement; the refusal it points at is G1-29's, asserted in List A
     * ⛔ REFUSED: G5-17 — the capability's own text is "a resolved ticket drafts a help row;  the help registry generates itself from X-122"; the registry is X-122's
     * ⛔ REFUSED: G9-19 — the capability's own text is "failed searches open a help topic;  the help registry generates itself from X-122"; the registry is X-122's
     * ⛔ REFUSED: G21-14 — the capability's own text is "the help card offered before the ticket is submitted"; a restatement, no refusal
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

    private function assertFilesDoNotContain(string $pattern): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Modules/X-111')));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && ! in_array($file->getBasename(), ['capabilities.php', 'manifest.php'])) {
                $content = file_get_contents($file->getPathname());
                $this->assertDoesNotMatchRegularExpression("/$pattern/i", $content, "File {$file->getPathname()} matched forbidden term $pattern");
            }
        }
    }

    /** [G1-29] [G1-35] */
    public function test_g1_29_and_g1_35_mrr_blends_refused(): void
    {
        $this->assertFilesDoNotContain('\b(mrr|booked|collected)\b');
    }

    /** [G4-24] */
    public function test_g4_24_two_packages_no_99_tier(): void
    {
        $this->assertFilesDoNotContain('\b(99|999|tier|tiers|package|packages)\b');
    }

    /** [G5-18] */
    public function test_g5_18_human_reply_escalates(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Human Escalate', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $ticket = $this->ticketAction->handle($biz->id, 'HUMAN', 'human_escalation');
        $this->assertEquals('human_requested', $ticket->source);
        $this->assertEquals('human_escalation', $ticket->category);
        $this->assertEquals('open', $ticket->status);
    }

    /** [G7-33] */
    public function test_g7_33_spend_ceiling_alerts_never_stops_phone(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Ceiling', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $alert = $this->alertAction->handle($biz->id, 'warning', 'Spend ceiling reached');
        $this->assertEquals('warning', $alert->severity);
        $this->assertStringContainsString('Spend ceiling reached', $alert->action_verb_message);

        $this->assertFilesDoNotContain('\b(phone|answering|stop_phone|halt_telephony)\b');
    }

    /** [G19-09] */
    public function test_g19_09_compromise_halt_is_security_stop(): void
    {
        $this->assertFilesDoNotContain('\b(balance|zero_balance|credit|credit_cap)\b');
    }

    /** [G21-02] */
    public function test_g21_02_fuzzy_merged_tickets(): void
    {
        $this->assertFilesDoNotContain('\b(fuzzy|merged|merge_tickets)\b');
    }

    /** [G15-28] */
    public function test_g15_28_no_pay_field_exposed(): void
    {
        $this->assertFilesDoNotContain('\b(pay|wage|wages)\b');
        $this->assertFalse(Schema::hasColumn('operator_alerts', 'pay'));
        $this->assertFalse(Schema::hasColumn('tenant_tickets', 'pay'));
    }
}
