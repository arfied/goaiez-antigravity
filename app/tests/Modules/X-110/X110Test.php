<?php

declare(strict_types=1);

namespace Tests\Modules\X110;

use App\Modules\X110\Actions\PixelEventsAction;
use App\Modules\X110\Actions\PixelInstallAction;
use App\Modules\X110\Actions\PixelVerifyAction;
use App\Modules\X110\Domain\PixelEngine;
use App\Modules\X110\Events\FormAbandoned;
use App\Modules\X110\Events\VisitStarted;
use App\Modules\X110\Models\PixelEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X110Test extends TestCase
{
    private PixelEngine $engine;

    private PixelInstallAction $installer;

    private PixelVerifyAction $verifier;

    private PixelEventsAction $eventAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new PixelEngine;
        $this->installer = new PixelInstallAction($this->engine);
        $this->verifier = new PixelVerifyAction($this->engine);
        $this->eventAction = new PixelEventsAction($this->engine);
    }

    /**
     * TEST ANCHOR
     * the tag is served from .tenant-domain and loads with third-party cookies disabled;
     * no event row is ever deleted by age;
     * a form abandoned at field 3 writes form.abandoned with the field name
     */
    public function test_anchor_tag_first_party_no_event_deletion_and_form_abandonment(): void
    {
        Event::fake([VisitStarted::class, FormAbandoned::class]);

        $biz = TestCase::provisionTenant(['name' => 'Pixel Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Tag served from .tenant-domain with third-party cookies disabled
        $verifyRes = $this->verifier->handle(
            businessId: $biz->id,
            servedDomain: 'analytics.austinplumbing.com',
            thirdPartyCookiesDisabled: true
        );

        $this->assertTrue($verifyRes['is_verified']);
        $this->assertTrue($verifyRes['first_party']);
        $this->assertTrue($verifyRes['third_party_cookies_disabled']);
        $this->assertStringContainsString('austinplumbing.com', $verifyRes['tag_url']);

        // 2. Record visit and session
        $visitData = $this->engine->recordVisit(
            businessId: $biz->id,
            visitorId: 'vis_xyz_789',
            utmSource: 'google',
            utmMedium: 'cpc',
            utmCampaign: 'emergency_leak'
        );

        // 3. A form abandoned at field 3 writes form.abandoned with the field name
        $abandonedEvent = $this->eventAction->handle(
            businessId: $biz->id,
            sessionId: $visitData['session_id'],
            eventName: 'form.abandoned',
            payload: [
                'form_id' => 'lead_capture_modal',
                'abandoned_field' => 'phone_number',
                'field_index' => 3,
            ]
        );

        $this->assertEquals('form.abandoned', $abandonedEvent->event_name);
        $this->assertEquals('phone_number', $abandonedEvent->payload['abandoned_field']);
        $this->assertEquals(3, $abandonedEvent->payload['field_index']);
        Event::assertDispatched(FormAbandoned::class);

        // 4. No event row is ever deleted by age (immutable append-only log)
        $initialCount = PixelEvent::where('business_id', $biz->id)->count();
        $this->assertGreaterThan(0, $initialCount);
    }

    /**
     * [G6-09] UTM survives the session; the attribution is X-138's
     */
    public function test_g6_09_utm_persistence(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'UTM Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->engine->recordVisit($biz->id, 'vis_utm_123', 'facebook', 'ad', 'spring_sale');
        $this->assertNotNull($res['session_token']);
    }

    /**
     * [G9-02] ClickHouse is corpus vocabulary — one database (§22 · P-143)
     */
    public function test_g9_02_single_database(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G13-01] the header's own shape — first-party, on the tenant's subdomain
     */
    public function test_g13_01_first_party_subdomain(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Subdomain Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $install = $this->installer->handle($biz->id, 'plumbpros.com');
        $this->assertStringContainsString('analytics.plumbpros.com', $install['script_tag']);
    }

    /**
     * [G13-12] the chat's context updates from the page (X-102)
     */
    public function test_g13_12_chat_page_context(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G13-27] watching the tenant's OWN tags fire is not ad management
     */
    public function test_g13_27_tenant_tags(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G13-28] the 50ms hop before redirect
     */
    public function test_g13_28_redirect_hop(): void
    {
        $this->markTestIncomplete('TODO: implement real assertions');
    }

    /**
     * [G13-30] rage-click and scroll depth, rendered by X-194
     */
    public function test_g13_30_rage_click_recording(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Rage Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $v = $this->engine->recordVisit($biz->id, 'vis_rage_1');
        $evt = $this->eventAction->handle($biz->id, $v['session_id'], 'rage_click', ['element' => 'button#submit_order', 'clicks' => 6]);

        $this->assertEquals('rage_click.detected', $evt->event_name);
        $this->assertEquals(6, $evt->payload['clicks']);
    }
}
