<?php

declare(strict_types=1);

namespace Tests\Modules\X137;

use App\Modules\X137\Actions\CallAttributeAction;
use App\Modules\X137\Actions\LinkQrAction;
use App\Modules\X137\Actions\LinkShortAction;
use App\Modules\X137\Events\CallAttributed;
use App\Modules\X137\Events\LinkClicked;
use App\Modules\X137\Events\VisitJoinedToCall;
use App\Modules\X137\Models\CallToken;
use App\Modules\X137\Models\LinkClick;
use App\Services\Config\DefaultsRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class X137Test extends TestCase
{
    private CallAttributeAction $attributeAction;

    private LinkShortAction $shortAction;

    private LinkQrAction $qrAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attributeAction = new CallAttributeAction(app(DefaultsRegistry::class));
        $this->shortAction = new LinkShortAction;
        $this->qrAction = new LinkQrAction;
    }

    public function test_anchor_call_attribution_ttl_and_visit_join(): void
    {
        Event::fake([CallAttributed::class, VisitJoinedToCall::class]);

        $biz = TestCase::provisionTenant(['name' => 'DNI Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $token = $this->attributeAction->allocateToken(
            businessId: $biz->id,
            visitorSessionToken: 'sess_abc123',
            allocatedNumber: '+15551112222',
            campaignSource: 'google_local_services',
            ttlMinutes: 30
        );

        $this->assertEquals('active', $token->status);

        $joinRes = $this->attributeAction->attributeCall($biz->id, 501, '+15551112222');
        $this->assertEquals('attributed', $joinRes['status']);
        $this->assertEquals('sess_abc123', $joinRes['visitor_session_token']);
        $this->assertEquals('google_local_services', $joinRes['campaign_source']);

        Event::assertDispatched(CallAttributed::class);
        Event::assertDispatched(VisitJoinedToCall::class);

        $expiredToken = CallToken::create([
            'business_id' => $biz->id,
            'visitor_session_token' => 'sess_expired999',
            'allocated_number' => '+15553334444',
            'campaign_source' => 'bing_ads',
            'whisper_text' => 'Call from bing_ads',
            'expires_at' => Carbon::now()->subMinutes(10), // Expired 10 min ago
            'status' => 'active',
        ]);

        $unattributedRes = $this->attributeAction->attributeCall($biz->id, 502, '+15553334444');
        $this->assertEquals('unattributed', $unattributedRes['status']);

        $tokenFresh = CallToken::where('business_id', $biz->id)->find($expiredToken->id);
        $this->assertEquals('expired_unattributed', $tokenFresh->status);
    }

    public function test_short_link_and_qr(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'QR Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $link = $this->shortAction->handle($biz->id, 'https://example.com/promo', 'billboard_q3');
        $this->assertNotEmpty($link->short_code);
        $this->assertEquals('billboard_q3', $link->campaign);

        $qr = $this->qrAction->handle($biz->id, $link->id);
        $this->assertStringContainsString('<svg', $qr);
    }

    /**
     * [G3-11]
     */
    public function test_g3_11_every_visitor_gets_a_call_token(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'G311 Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $token1 = $this->attributeAction->allocateToken($biz->id, 'v1', '+15550001111');
        $token2 = $this->attributeAction->allocateToken($biz->id, 'v2', '+15550002222');

        $this->assertEquals('active', $token1->status);
        $this->assertEquals('active', $token2->status);

        $res = $this->attributeAction->attributeCall($biz->id, 999, '+15550009999');
        $this->assertEquals('unattributed', $res['status']);
    }

    /**
     * [G8-13]
     */
    public function test_g8_13_dni_token_per_visitor(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'G813 Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->attributeAction->allocateToken($biz->id, 'vA', '+15551230001', 'src_a');
        $this->attributeAction->allocateToken($biz->id, 'vB', '+15551230002', 'src_b');

        $resA = $this->attributeAction->attributeCall($biz->id, 101, '+15551230001');
        $this->assertEquals('vA', $resA['visitor_session_token']);
        $this->assertEquals('src_a', $resA['campaign_source']);

        $resB = $this->attributeAction->attributeCall($biz->id, 102, '+15551230002');
        $this->assertEquals('vB', $resB['visitor_session_token']);
        $this->assertEquals('src_b', $resB['campaign_source']);
    }

    /**
     * [G13-19]
     */
    public function test_g13_19_number_pool_token(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'G1319 Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $token1 = $this->attributeAction->allocateToken($biz->id, 'v1', '+15558880000');
        $token2 = $this->attributeAction->allocateToken($biz->id, 'v2', '+15558880000');

        $res = $this->attributeAction->attributeCall($biz->id, 201, '+15558880000');
        $this->assertEquals('attributed', $res['status']);
        $this->assertEquals('v2', $res['visitor_session_token']);

        $t1Fresh = CallToken::find($token1->id);
        $t2Fresh = CallToken::find($token2->id);

        $this->assertEquals('active', $t1Fresh->status);
        $this->assertNull($t1Fresh->joined_call_id);

        $this->assertEquals('joined', $t2Fresh->status);
        $this->assertEquals(201, $t2Fresh->joined_call_id);
    }

    /**
     * [G18-17]
     */
    public function test_g18_17_whisper_names_the_source(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'G1817 Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $t1 = $this->attributeAction->allocateToken($biz->id, 'v1', '+15559990001', 'bing_ads');
        $this->assertStringContainsString('bing_ads', $t1->whisper_text);

        $t2 = $this->attributeAction->allocateToken($biz->id, 'v2', '+15559990002', 'google_lsa');
        $this->assertStringContainsString('google_lsa', $t2->whisper_text);
        $this->assertStringNotContainsString('bing_ads', $t2->whisper_text);
    }

    /**
     * [G18-24]
     */
    public function test_g18_24_one_whisper_spec(): void
    {
        Event::fake([CallAttributed::class]);
        Http::fake();

        $biz = TestCase::provisionTenant(['name' => 'G1824 Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $token = $this->attributeAction->allocateToken($biz->id, 'v1', '+15557770001', 'fb_ads');

        $this->attributeAction->attributeCall($biz->id, 301, '+15557770001');

        Event::assertDispatched(CallAttributed::class, fn ($e) => $e->whisperText === $token->whisper_text);
        Http::assertNothingSent();
    }

    /**
     * [G13-24]
     */
    public function test_g13_24_offline_campaign_reuses_token(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'G1324 Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $t1 = $this->attributeAction->allocateToken($biz->id, 'v1', '+15554440000', 'print_ad', 30, true);
        $t2 = $this->attributeAction->allocateToken($biz->id, 'v2', '+15554449999', 'print_ad', 30, true);

        $this->assertEquals('+15554440000', $t1->allocated_number);
        $this->assertEquals('+15554440000', $t2->allocated_number);
        $this->assertNotEquals($t1->id, $t2->id);

        $t3 = $this->attributeAction->allocateToken($biz->id, 'v3', '+15554448888', 'print_ad', 30, false);
        $this->assertEquals('+15554448888', $t3->allocated_number);
    }

    /**
     * [G13-24]
     */
    public function test_g13_24_short_code_is_redeemable_only_under_its_own_business(): void
    {
        $a = TestCase::provisionTenant(['name' => 'Tenant A', 'currency' => 'USD']);
        $b = TestCase::provisionTenant(['name' => 'Tenant B', 'currency' => 'USD']);

        DB::statement("SET app.business_id = '{$a->id}'");
        $link = $this->shortAction->handle($a->id, 'https://example.com/dest', 'flyer_a');

        DB::statement("SET app.business_id = '{$b->id}'");
        $this->get("/l/{$b->id}/{$link->short_code}")->assertNotFound();
        $this->assertEquals(0, LinkClick::count());

        DB::statement("SET app.business_id = '{$a->id}'");
        $this->get("/l/{$a->id}/{$link->short_code}")->assertRedirect('https://example.com/dest');
    }

    public function test_a_cold_link_click_is_recorded_without_a_session(): void
    {
        Event::fake([LinkClicked::class]);

        $biz = TestCase::provisionTenant(['name' => 'Cold Click', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $link = $this->shortAction->handle($biz->id, 'https://example.com/dest', 'flyer_a');

        // no session is present or required for a cold link click
        $response = $this->get("/l/{$biz->id}/{$link->short_code}");
        $response->assertRedirect('https://example.com/dest');

        $this->assertEquals(1, LinkClick::count());
        $click = LinkClick::first();
        $this->assertEquals($biz->id, $click->business_id);
        $this->assertEquals($link->id, $click->short_link_id);

        Event::assertDispatched(LinkClicked::class, function ($e) use ($biz, $link) {
            return $e->businessId === $biz->id && $e->shortLinkId === $link->id && $e->shortCode === $link->short_code;
        });

        $this->get("/l/{$biz->id}/unknown_code")->assertNotFound();
    }

    #[Test]
    #[Group('G13-24')]
    public function test_static_number_per_offline_campaign(): void
    {
        $biz = TestCase::provisionTenant();
        DB::statement("SET app.business_id = '{$biz->id}'");

        $token = $this->attributeAction->allocateStaticToken(
            businessId: $biz->id,
            allocatedNumber: '+15550000010',
            campaignSource: 'billboard_q3'
        );

        $this->assertTrue($token->is_static);
        $this->assertNull($token->expires_at);

        // Multiple calls can be attributed without changing the token status to joined
        $res1 = $this->attributeAction->attributeCall($biz->id, 1001, '+15550000010');
        $this->assertEquals('attributed', $res1['status']);
        $this->assertEquals('billboard_q3', $res1['campaign_source']);

        $res2 = $this->attributeAction->attributeCall($biz->id, 1002, '+15550000010');
        $this->assertEquals('attributed', $res2['status']);

        $token->refresh();
        $this->assertEquals('active', $token->status);
        $this->assertNull($token->joined_call_id);
    }

    public function test_a_short_link_with_a_blank_destination_is_refused_before_it_is_counted(): void
    {
        Event::fake([LinkClicked::class]);

        $biz = TestCase::provisionTenant(['name' => 'Cold Click', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $link = $this->shortAction->handle($biz->id, '   ', 'flyer_a');

        $response = $this->get("/l/{$biz->id}/{$link->short_code}");

        $this->assertEquals(0, LinkClick::count(), 'Blank destination counted as click');
        Event::assertNotDispatched(LinkClicked::class);
        $response->assertNotFound();
    }
}
