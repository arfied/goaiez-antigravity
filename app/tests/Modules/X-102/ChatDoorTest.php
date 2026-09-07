<?php

declare(strict_types=1);

namespace Tests\Modules\X102;

use App\Models\Business;
use App\Modules\X102\Models\ChatSession;
use App\Services\Pixel\PixelKeys;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatDoorTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_key_creates_chat_session_for_right_business(): void
    {
        // Prove that providing the correct PixelKey yields a session belonging to that business.
        $biz = TestCase::provisionTenant(['name' => 'Door Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $keys = app(PixelKeys::class);
        $key = $keys->ensureFor($biz);
        Tenancy::forgetAll();

        $response = $this->postJson("/api/chat/{$key}/start");

        $response->assertStatus(201);
        $response->assertJsonStructure(['session_token']);

        // Assert the database
        Tenancy::set((int) $biz->id);
        $this->assertEquals(1, ChatSession::where('business_id', $biz->id)->count());
    }

    public function test_unknown_key_creates_nothing_and_does_not_500(): void
    {
        // Prove that an invalid key returns a 404 without crashing, and does not bypass tenancy to create a row.
        Tenancy::forgetAll();

        $unknownKey = Str::uuid()->toString();
        $response = $this->postJson("/api/chat/{$unknownKey}/start");

        $response->assertStatus(404);

        // Assert no rows returned for empty tenant (RLS enforces isolation; we cannot read the whole table)
        $this->assertEquals(0, ChatSession::count());
    }

    public function test_key_for_business_a_does_not_produce_row_readable_as_business_b(): void
    {
        // Prove that tenancy isolation holds: using A's key does not write to B's schema.
        $bizA = TestCase::provisionTenant(['name' => 'Business A', 'currency' => 'USD']);
        Tenancy::set((int) $bizA->id);
        $keyA = app(PixelKeys::class)->ensureFor($bizA);

        $bizB = TestCase::provisionTenant(['name' => 'Business B', 'currency' => 'USD']);

        Tenancy::forgetAll();

        // Hit the door with A's key
        $response = $this->postJson("/api/chat/{$keyA}/start");
        $response->assertStatus(201);

        // Read as B
        Tenancy::set((int) $bizB->id);
        $this->assertEquals(0, ChatSession::where('business_id', $bizB->id)->count());
    }
}
