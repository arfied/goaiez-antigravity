<?php

declare(strict_types=1);

namespace Tests\Modules\X196;

use App\Modules\X196\Actions\ExtensionInjectAction;
use App\Modules\X196\Actions\ExtensionScanAction;
use App\Modules\X196\Events\ExtensionAborted;
use App\Modules\X196\Events\ExtensionTriggered;
use App\Modules\X196\Events\ProspectInjected;
use App\Modules\X196\Models\ExtensionSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class X196Test extends TestCase
{
    private ExtensionScanAction $scanAction;

    private ExtensionInjectAction $injectAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanAction = new ExtensionScanAction;
        $this->injectAction = new ExtensionInjectAction;
    }

    /**
     * TEST ANCHOR
     * a simulated rate-limit banner stops all extension activity within one action and writes extension.aborted;
     * the actions-per-minute cap is a constant in the extension bundle, asserted by a test on the build artifact
     */
    public function test_anchor_rate_limit_stops_all_activity_and_cap_is_constant(): void
    {
        Event::fake([ExtensionTriggered::class, ProspectInjected::class, ExtensionAborted::class]);

        $biz = TestCase::provisionTenant(['name' => 'Browser Extension Ingest Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Constant in bundle asserted by test (TEST ANCHOR)
        $this->assertEquals(30, ExtensionSession::ACTIONS_PER_MINUTE_CAP, 'Actions-per-minute cap is a constant in the extension bundle (TEST ANCHOR)');

        // 2. Extension session: FetchPolicy.authenticated=false by default (G3-05, P-078, G12-08)
        $session = ExtensionSession::create([
            'business_id' => $biz->id,
            'session_token' => 'ext_tok_'.Str::random(12),
        ]);
        $this->assertFalse($session->is_authenticated_scrape, 'authenticated=false by default (P-078, G3-05)');

        // 3. Normal DOM scan and prospect injection with attestation_id (G2-03, P-069)
        $this->scanAction->scanPage($biz->id, $session->id, 'https://prospect-directory.com/plumbing', '<html><body><h3>Plumbing Pro</h3></body></html>');
        Event::assertDispatched(ExtensionTriggered::class);

        $injection = $this->injectAction->injectProspect(
            businessId: $biz->id,
            sessionId: $session->id,
            sourceUrl: 'https://prospect-directory.com/plumbing',
            attestationId: 'attest_ext_'.Str::random(8), // G2-03, P-069
            prospectPayload: ['company' => 'Plumbing Pro', 'phone' => '555-0144']
        );
        $this->assertNotNull($injection);
        Event::assertDispatched(ProspectInjected::class);

        // 4. Rate-limit banner stops all activity within 1 action and writes extension.aborted (TEST ANCHOR)
        $simulatedBlockedDom = '<html><body><div class="block-banner">Rate Limit Exceeded. Too many requests.</div></body></html>';
        $abortedSession = $this->scanAction->scanPage($biz->id, $session->id, 'https://prospect-directory.com/blocked', $simulatedBlockedDom);

        $this->assertTrue($abortedSession->is_aborted, 'Rate-limit banner triggers abort (TEST ANCHOR)');
        $this->assertFalse($abortedSession->is_active);
        Event::assertDispatched(ExtensionAborted::class);

        // 5. Subsequent injection on aborted session is refused
        try {
            $this->injectAction->injectProspect(
                businessId: $biz->id,
                sessionId: $session->id,
                sourceUrl: 'https://prospect-directory.com/blocked',
                attestationId: 'attest_fail_123',
                prospectPayload: ['company' => 'Ignored']
            );
            $this->fail('Expected InvalidArgumentException on aborted session');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('aborted', $e->getMessage());
        }
    }

    /**
     * [G2-03], [G3-05], [G3-24], [G3-32], [G3-39], [G12-08]
     */
}
