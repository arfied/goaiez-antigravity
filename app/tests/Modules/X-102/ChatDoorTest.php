<?php

declare(strict_types=1);

namespace Tests\Modules\X102;

use App\Models\Business;
use App\Modules\CAgent\Models\AgentTurn;
use App\Modules\X102\Models\ChatSession;
use App\Modules\X102\Models\ChatTurn;
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
        // RLS prevents reading the whole table to prove "no rows anywhere" with an empty tenant.
        // Instead, we provision the only tenant (which Business::first() will deterministically fall back to) and assert as that tenant,
        // and we place this before the status check so a bypass fails here first.
        $biz = TestCase::provisionTenant(['name' => 'Bypass Target', 'currency' => 'USD']);
        Tenancy::forgetAll();

        $unknownKey = Str::uuid()->toString();
        $response = $this->postJson("/api/chat/{$unknownKey}/start");

        // Assert the database first, so a bypass fails here rather than on the status code.
        Tenancy::set((int) $biz->id);
        $this->assertEquals(0, ChatSession::count());

        $response->assertStatus(404);
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

    public function test_valid_key_creates_chat_turn_for_session(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Turn Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $keys = app(PixelKeys::class);
        $key = $keys->ensureFor($biz);

        $session = ChatSession::create([
            'business_id' => $biz->id,
            'session_token' => 'sess_turn_test',
            'status' => 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => false,
        ]);
        Tenancy::forgetAll();

        $response = $this->postJson("/api/chat/{$key}/turn", [
            'session_token' => 'sess_turn_test',
            'message' => 'Hello from visitor',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['id']);

        Tenancy::set((int) $biz->id);
        $this->assertEquals(1, ChatTurn::where('chat_session_id', $session->id)->count());
        $turn = ChatTurn::first();
        $this->assertEquals('Hello from visitor', $turn->message);
        $this->assertEquals('visitor', $turn->author_type);

        // The wire to C-Agent is synchronous and works on the live path
        $this->assertEquals(1, AgentTurn::where('business_id', $biz->id)->count());
    }

    public function test_key_for_business_a_and_session_for_business_b_returns_404(): void
    {
        $bizA = TestCase::provisionTenant(['name' => 'Business A', 'currency' => 'USD']);
        Tenancy::set((int) $bizA->id);
        $keyA = app(PixelKeys::class)->ensureFor($bizA);

        $bizB = TestCase::provisionTenant(['name' => 'Business B', 'currency' => 'USD']);
        Tenancy::set((int) $bizB->id);
        ChatSession::create([
            'business_id' => $bizB->id,
            'session_token' => 'sess_biz_b',
            'status' => 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => false,
        ]);

        Tenancy::forgetAll();

        $response = $this->postJson("/api/chat/{$keyA}/turn", [
            'session_token' => 'sess_biz_b',
            'message' => 'Hello',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['error' => 'Session not found']);
    }

    /**
     * The count assertion can fail on its own terms. If the guard is bypassed,
     * Eloquent finds the session (treating the integer token as a string), and
     * ChatTurnAction::handle() succeeds because it receives the session's integer ID,
     * not the non-string token, avoiding a TypeError.
     */
    public function test_non_string_inputs_return_400(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Bad Request Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $key = app(PixelKeys::class)->ensureFor($biz);

        $session = ChatSession::create([
            'business_id' => $biz->id,
            'session_token' => '123',
            'status' => 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => false,
        ]);
        Tenancy::forgetAll();

        // Send integer as session_token
        $response = $this->postJson("/api/chat/{$key}/turn", [
            'session_token' => 123,
            'message' => 'Hello',
        ]);

        // Assert the database first, so a bypass fails here rather than on the status code.
        Tenancy::set((int) $biz->id);
        $this->assertEquals(0, ChatTurn::where('chat_session_id', $session->id)->count());

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Bad Request']);
    }

    /**
     * Established wave 138d: the general claim that the count assertion cannot fail on its own
     * terms is false. A controller mutation replacing the message input with the session token
     * bypasses both the 400 check and handle()'s strict type check, allowing a row to be
     * inserted and reddening the count assertion.
     */
    public function test_non_string_message_returns_400(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Bad Message Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $key = app(PixelKeys::class)->ensureFor($biz);

        $session = ChatSession::create([
            'business_id' => $biz->id,
            'session_token' => 'sess_bad_msg',
            'status' => 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => false,
        ]);
        Tenancy::forgetAll();

        // Send integer as message
        $response = $this->postJson("/api/chat/{$key}/turn", [
            'session_token' => 'sess_bad_msg',
            'message' => 123,
        ]);

        // Assert the database first, so a bypass fails here rather than on the status code.
        Tenancy::set((int) $biz->id);
        $this->assertEquals(0, ChatTurn::where('chat_session_id', $session->id)->count());

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Bad Request']);
    }
}
