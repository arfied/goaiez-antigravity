<?php

declare(strict_types=1);

namespace Tests\Modules\X196;

use App\Services\Pixel\PixelKeys;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class ExtensionRouteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_valid_pixel_key_opens_a_session_and_returns_its_token_once(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Door Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $keys = app(PixelKeys::class);
        $key = $keys->ensureFor($biz);
        Tenancy::forgetAll();

        $response = $this->postJson("/api/extension/{$key}/session");
        $response->assertStatus(201);
        $token = $response->json('session_token');
        $this->assertIsString($token);
        $this->assertSame(48, strlen($token));

        Tenancy::set((int) $biz->id);
        $this->assertDatabaseHas('extension_sessions', [
            'business_id' => $biz->id,
            'session_token' => $token,
            'is_active' => true,
        ]);
    }

    public function test_an_unknown_key_is_404(): void
    {
        $this->postJson('/api/extension/not-a-key-4615/session')->assertStatus(404);
    }

    public function test_a_scan_increments_the_session_and_a_rate_limit_banner_aborts_it(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Door Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $keys = app(PixelKeys::class);
        $key = $keys->ensureFor($biz);
        Tenancy::forgetAll();

        $response = $this->postJson("/api/extension/{$key}/session");
        $token = $response->json('session_token');

        $this->postJson("/api/extension/{$key}/scan", [
            'session_token' => $token,
            'page_url' => 'https://distinctive-4615.example/p',
            'dom' => '<html>ordinary page</html>',
        ])->assertStatus(200)->assertJson(['is_active' => true, 'actions_count' => 1]);

        $this->postJson("/api/extension/{$key}/scan", [
            'session_token' => $token,
            'page_url' => 'https://distinctive-4615.example/p2',
            'dom' => '<div class="block-banner">rate limit exceeded</div>',
        ])->assertJson(['is_active' => false, 'is_aborted' => true]);

        Tenancy::set((int) $biz->id);
        $this->assertDatabaseHas('extension_sessions', [
            'session_token' => $token,
            'is_aborted' => true,
        ]);
    }

    public function test_a_session_token_from_another_tenant_is_404(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Door Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $keys = app(PixelKeys::class);
        $key = $keys->ensureFor($biz);
        Tenancy::forgetAll();

        $response = $this->postJson("/api/extension/{$key}/session");
        $tokenA = $response->json('session_token');

        $bizB = TestCase::provisionTenant(['name' => 'Door Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $bizB->id);
        $keyB = $keys->ensureFor($bizB);
        Tenancy::forgetAll();

        $this->postJson("/api/extension/{$keyB}/scan", [
            'session_token' => $tokenA,
            'page_url' => 'https://x.example',
            'dom' => 'x',
        ])->assertStatus(404);
    }
}
