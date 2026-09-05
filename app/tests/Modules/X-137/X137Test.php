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

        $qr = $this->qrAction->handle($biz->id, $link->id);
        $this->assertStringContainsString('<svg', $qr);
    }

    public function test_header_capabilities(): void
    {
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
