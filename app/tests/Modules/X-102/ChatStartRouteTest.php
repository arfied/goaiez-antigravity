<?php

declare(strict_types=1);

namespace Tests\Modules\X102;

use App\Modules\X102\Models\ChatSession;
use App\Services\Pixel\PixelKeys;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ChatStartRouteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_array_pixel_session_token_returns_201(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Door Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $keys = app(PixelKeys::class);
        $key = $keys->ensureFor($biz);
        Tenancy::forgetAll();

        $response = $this->postJson("/api/chat/{$key}/start", [
            'pixel_session_token' => ['array_value'],
        ]);

        $response->assertStatus(201);
    }

    public function test_string_pixel_session_token_saves_value_and_returns_201(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Door Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $keys = app(PixelKeys::class);
        $key = $keys->ensureFor($biz);
        Tenancy::forgetAll();

        $response = $this->postJson("/api/chat/{$key}/start", [
            'pixel_session_token' => 'a_real_string_token',
        ]);

        Tenancy::set((int) $biz->id);
        $session = ChatSession::where('business_id', $biz->id)->first();
        $this->assertEquals('a_real_string_token', $session->pixel_session_token);

        $response->assertStatus(201);
    }
}
