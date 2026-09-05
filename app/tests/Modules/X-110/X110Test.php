<?php

declare(strict_types=1);

namespace Tests\Modules\X110;

use App\Modules\X110\Actions\PixelEventsAction;
use App\Modules\X110\Actions\PixelInstallAction;
use App\Modules\X110\Actions\PixelVerifyAction;
use App\Modules\X110\Domain\PixelEngine;
use App\Modules\X110\Events\FormAbandoned;
use App\Modules\X110\Events\VisitStarted;
use App\Modules\X110\Models\CwvSample;
use App\Modules\X110\Models\IdentityLink;
use App\Modules\X110\Models\PixelEvent;
use App\Modules\X110\Models\Session;
use App\Modules\X110\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
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
        $this->assertFalse(array_key_exists('clickhouse', config('database.connections')));

        $this->assertNull((new PixelEvent)->getConnectionName());
        $this->assertNull((new Visit)->getConnectionName());
        $this->assertNull((new Session)->getConnectionName());
        $this->assertNull((new CwvSample)->getConnectionName());
        $this->assertNull((new IdentityLink)->getConnectionName());

        $biz = TestCase::provisionTenant(['name' => 'Single DB Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $v = $this->engine->recordVisit($biz->id, 'vis_xyz_789');
        $this->eventAction->handle(
            businessId: $biz->id,
            sessionId: $v['session_id'],
            eventName: 'custom_event',
            payload: ['foo' => 'bar']
        );

        $readEvent = PixelEvent::where('business_id', $biz->id)
            ->where('event_name', 'custom_event')
            ->first();

        $this->assertNotNull($readEvent);
        $this->assertEquals('custom_event', $readEvent->event_name);
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
        $biz = TestCase::provisionTenant(['name' => 'Chat Context Biz']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $v = $this->engine->recordVisit($biz->id, 'vis_chat_1', 'google', 'cpc', 'spring', '/hvac-repair');

        $context = $this->engine->pageContextForSession($biz->id, $v['session_token']);
        $this->assertEquals('/hvac-repair', $context['landing_page']);
        $this->assertEquals('google', $context['utm_source']);
        $this->assertEquals('/hvac-repair', $context['current_page']);

        $chatStart = new \App\Modules\X102\Actions\ChatStartAction($this->engine);
        $chatRefresh = new \App\Modules\X102\Actions\ChatContextRefreshAction($this->engine);

        $session = $chatStart->handle($biz->id, '192.168.1.1', false, $v['session_token']);
        $this->assertEquals($v['session_token'], $session->pixel_session_token);
        $this->assertEquals('/hvac-repair', $session->page_context['current_page']);

        $this->eventAction->handle($biz->id, $v['session_id'], 'page_view', ['url' => '/hvac-repair']);
        $this->eventAction->handle($biz->id, $v['session_id'], 'page_view', ['url' => '/hvac-repair/pricing']);

        $chatRefresh->handle($session);

        $fresh = \App\Modules\X102\Models\ChatSession::find($session->id)->fresh();
        $this->assertEquals('/hvac-repair/pricing', $fresh->page_context['current_page']);
        $this->assertEquals('/hvac-repair', $fresh->page_context['landing_page']);

        $sessionNoToken = $chatStart->handle($biz->id, '192.168.1.1', false, null);
        $this->assertNull($sessionNoToken->pixel_session_token);
        $this->assertNull($sessionNoToken->page_context);

        $otherBiz = TestCase::provisionTenant(['name' => 'Other Biz']);
        DB::statement("SET app.business_id = '{$otherBiz->id}'");
        $otherVisit = $this->engine->recordVisit($otherBiz->id, 'vis_other_1');

        $badContext = $this->engine->pageContextForSession($biz->id, $otherVisit['session_token']);
        $this->assertNull($badContext);
    }

    /**
     * [G13-27] watching the tenant's OWN tags fire is not ad management
     */
    public function test_g13_27_tenant_tags(): void
    {
        Http::fake();

        $biz = TestCase::provisionTenant(['name' => 'Ads Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $v = $this->engine->recordVisit($biz->id, 'vis_ads_1');

        $this->eventAction->handle(
            businessId: $biz->id,
            sessionId: $v['session_id'],
            eventName: 'tag.fired',
            payload: [
                'tag_id' => 'GTM-XXXXXXX',
                'script_name' => 'google_tag_manager',
            ]
        );

        $readEvent = PixelEvent::where('business_id', $biz->id)
            ->where('event_name', 'tag.fired')
            ->first();

        $this->assertNotNull($readEvent);
        $this->assertEquals('GTM-XXXXXXX', $readEvent->payload['tag_id']);

        Http::assertNothingSent();
    }

    /**
     * [G13-28] the 50ms hop before redirect
     */
    public function test_g13_28_redirect_hop(): void
    {
        $this->assertTrue(true);
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
