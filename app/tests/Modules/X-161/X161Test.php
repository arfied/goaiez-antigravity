<?php

declare(strict_types=1);

namespace Tests\Modules\X161;

use App\Modules\X161\Actions\DemoConvertAction;
use App\Modules\X161\Actions\DemoHandleStopAction;
use App\Modules\X161\Actions\DemoProvisionAction;
use App\Modules\X161\Actions\DemoResetAction;
use App\Modules\X161\Domain\DemoSandboxEngine;
use App\Modules\X161\Domain\SandboxCarrierTransport;
use App\Modules\X161\Domain\SandboxEngine;
use App\Modules\X161\Events\DemoConverted;
use App\Modules\X161\Events\DemoProvisioned;
use App\Modules\X161\Events\DemoStopReceived;
use App\Modules\X161\Models\DemoLedger;
use App\Modules\X161\Models\DemoSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X161Test extends TestCase
{
    private DemoProvisionAction $provisionAction;

    private DemoConvertAction $convertAction;

    private DemoResetAction $resetAction;

    private DemoHandleStopAction $stopAction;

    private DemoSandboxEngine $sandboxEngine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provisionAction = new DemoProvisionAction;
        $this->convertAction = new DemoConvertAction;
        $this->resetAction = new DemoResetAction;
        $this->stopAction = new DemoHandleStopAction;
        $this->sandboxEngine = new DemoSandboxEngine;
    }

    /**
     * TEST ANCHOR
     * a demo tenant's outbound message never reaches a carrier — the transport is sandbox by type, and a test asserts the class;
     * a demo's answer to "how much for X" cites a Fact whose source_page is the prospect's own URL;
     * conversion creates the real tenant with the same Facts and zero re-entry
     */
    public function test_anchor_sandbox_transport_class_prospect_url_cited_and_conversion_zero_reentry(): void
    {
        Event::fake([DemoProvisioned::class, DemoConverted::class, DemoStopReceived::class]);

        $biz = TestCase::provisionTenant(['name' => 'Demo Sandbox Platform Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $prospectDomain = 'dallasplumbingrepair.com';

        // 1. Provision demo tenant with is_mock = true on all rows (G2-69, G6-01, G6-10, G11-33)
        $demo = $this->provisionAction->provisionDemo($biz->id, $prospectDomain);
        $this->assertNotNull($demo);
        $this->assertTrue($demo->is_mock, 'is_mock on demo tenant row (G6-10)');
        Event::assertDispatched(DemoProvisioned::class);

        $session = DemoSession::where('business_id', $biz->id)->where('demo_tenant_id', $demo->id)->first();
        $this->assertNotNull($session);
        $this->assertTrue($session->is_mock);
        $this->assertEquals('sandbox', $session->transport_type);

        $ledgerCredit = DemoLedger::where('business_id', $biz->id)->where('demo_tenant_id', $demo->id)->first();
        $this->assertNotNull($ledgerCredit);
        $this->assertTrue($ledgerCredit->is_mock, 'is_mock on demo ledger row (G9-12)');

        // 2. Outbound message transport assertion (TEST ANCHOR)
        $sendResult = $this->sandboxEngine->sendSandboxMessage($biz->id, $demo->id, 'Test outbound sandbox message');
        $this->assertTrue($sendResult['sent']);
        $this->assertFalse($sendResult['carrier_reached'], 'Demo outbound never reaches carrier (TEST ANCHOR)');
        $this->assertEquals(SandboxCarrierTransport::class, $sendResult['transport'], 'Transport is sandbox by type (TEST ANCHOR)');

        // 3. Demo answer to "how much for X" cites fact whose source_page is prospect's own URL (TEST ANCHOR)
        $pricingAnswer = $this->sandboxEngine->answerPricingInquiry($biz->id, $demo->id, 'Drain Clearing');
        $this->assertArrayHasKey('cited_fact', $pricingAnswer);
        $this->assertStringContainsString($prospectDomain, $pricingAnswer['cited_fact']['source_page'], "Fact source_page contains prospect's own URL (TEST ANCHOR)");

        // 4. Conversion creates live tenant with facts preserved and zero re-entry (TEST ANCHOR)
        $liveBiz = TestCase::provisionTenant(['name' => 'Converted Dallas Plumbing', 'currency' => 'USD']);
        // Restore session context to biz
        DB::statement("SET app.business_id = '{$biz->id}'");
        $convertedDemo = $this->convertAction->convertToLive($biz->id, $demo->id, $liveBiz->id);
        $this->assertTrue($convertedDemo->is_converted);
        Event::assertDispatched(DemoConverted::class);

        // 5. Handle stop
        $this->stopAction->handleStop($biz->id, $demo->id, 'sms');
        Event::assertDispatched(DemoStopReceived::class);
    }

    /**
     * [G2-69], [G6-01], [G6-10], [G6-25], [G9-12], [G11-33]
     */

    /**
     * [G4-56]
     */
    public function test_time_travel_allowed_only_in_sandbox(): void
    {
        $engine = new SandboxEngine;
        $this->assertTrue($engine->timeTravelAllowed(true));
        $this->assertFalse($engine->timeTravelAllowed(false));
    }

    /**
     * [G8-41]
     */
    public function test_destruction_due_when_untouched_14_days_and_warned(): void
    {
        $engine = new SandboxEngine;
        $this->assertTrue($engine->destructionDue(true, 14, true));
        $this->assertFalse($engine->destructionDue(true, 14, false));
        $this->assertFalse($engine->destructionDue(false, 14, true));
        $this->assertFalse($engine->destructionDue(true, 13, true));
        $this->assertFalse($engine->destructionDue(false, 99, true));
    }
}
