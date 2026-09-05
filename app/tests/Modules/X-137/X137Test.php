<?php

declare(strict_types=1);

namespace Tests\Modules\X137;

use App\Modules\X137\Actions\CallAttributeAction;
use App\Modules\X137\Actions\LinkQrAction;
use App\Modules\X137\Actions\LinkShortAction;
use App\Modules\X137\Domain\X137Engine;
use App\Modules\X137\Events\CallAttributed;
use App\Modules\X137\Events\VisitJoinedToCall;
use App\Modules\X137\Models\CallToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class X137Test extends TestCase
{
    private CallAttributeAction $attributeAction;

    private LinkShortAction $shortAction;

    private LinkQrAction $qrAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attributeAction = new CallAttributeAction;
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

    public function test_header_capabilities(): void
    {
        require_once app_path('Modules/X-137/Domain/X137Engine.php');
        $engine = new X137Engine;
        $methods = ['enforceG3_11', 'enforceG8_13', 'enforceG13_19', 'enforceG13_24', 'enforceG18_17', 'enforceG18_24'];

        foreach ($methods as $method) {
            try {
                $engine->$method();
                $this->fail("Should throw for $method");
            } catch (\DomainException $e) {
                $this->assertStringContainsString('[G', $e->getMessage());
            }
        }
    }
}
