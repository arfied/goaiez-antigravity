<?php

declare(strict_types=1);

namespace Tests\Modules\X102;

use App\Models\Business;
use App\Modules\CAgent\Models\AgentTurn;
use App\Modules\X102\Models\ChatLead;
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

    /**
     * CLOSED: X-102 chat capture — the 400 Bad Request returned by the middleware is correct behavior, making the internal 500 safely unreachable.
     * BUILD PROPOSAL: X-102's ChatTurnAction calls C-Agent unconditionally; it should only call if the author is 'visitor'. Owner: X-102
     * BUILD PROPOSAL: mapping chat_session_id to C-Agent's conversation_id so HUMAN_TAKEOVER_LATCH works is required, but it has not been asked for yet. Owner: Track 1
     * BUILD PROPOSAL: X-102's ChatTurnAction defaults the turn number to 1; it should compute and pass the real turn number. Owner: X-102
     * BUILD PROPOSAL: X-01 cannot listen to ChatTurnCreated and use ingestMessage because web visitors only have a session token, which ingestMessage would wrongly insert into the Person phone column since it lacks an '@'. Owner: X-01
     * BUILD PROPOSAL: ChatTurnCreated carries no message text, but could safely do so because the AgentTurns law prohibits unencrypted text in job payloads, not synchronous event payloads (EmailReplied safely carries text). Owner: X-102
     */
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
        $agentTurn = AgentTurn::first();
        $this->assertEquals('Hello from visitor', $agentTurn->user_message);
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

    public function test_valid_key_creates_chat_lead_for_session(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Lead Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $key = app(PixelKeys::class)->ensureFor($biz);

        $session = ChatSession::create([
            'business_id' => $biz->id,
            'session_token' => 'sess_lead_test',
            'status' => 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => false,
        ]);
        Tenancy::forgetAll();

        $response = $this->postJson("/api/chat/{$key}/capture", [
            'session_token' => 'sess_lead_test',
            'name' => 'John Doe',
            'phone' => '1234567890',
            'email' => 'john@example.com',
            'message' => 'Hello',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['id']);

        Tenancy::set((int) $biz->id);
        $this->assertEquals(1, ChatLead::where('chat_session_id', $session->id)->count());
        $lead = ChatLead::first();
        $this->assertEquals('John Doe', $lead->name);
        $this->assertEquals('1234567890', $lead->phone);
    }

    public function test_non_string_capture_session_token_returns_400(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Lead Tenant', 'currency' => 'USD']);
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

        $response = $this->postJson("/api/chat/{$key}/capture", [
            'session_token' => 123,
            'name' => 'John Doe',
            'phone' => '1234567890',
        ]);

        Tenancy::set((int) $biz->id);
        $this->assertEquals(0, ChatLead::where('chat_session_id', $session->id)->count());

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Bad Request']);
    }

    public function test_non_string_capture_name_returns_400(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Lead Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $key = app(PixelKeys::class)->ensureFor($biz);

        $session = ChatSession::create([
            'business_id' => $biz->id,
            'session_token' => 'sess_lead_test',
            'status' => 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => false,
        ]);
        Tenancy::forgetAll();

        $response = $this->postJson("/api/chat/{$key}/capture", [
            'session_token' => 'sess_lead_test',
            'name' => 123,
            'phone' => '1234567890',
        ]);

        Tenancy::set((int) $biz->id);
        $this->assertEquals(0, ChatLead::where('chat_session_id', $session->id)->count());

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Bad Request']);
    }

    public function test_non_string_capture_phone_returns_400(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Lead Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $key = app(PixelKeys::class)->ensureFor($biz);

        $session = ChatSession::create([
            'business_id' => $biz->id,
            'session_token' => 'sess_lead_test',
            'status' => 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => false,
        ]);
        Tenancy::forgetAll();

        $response = $this->postJson("/api/chat/{$key}/capture", [
            'session_token' => 'sess_lead_test',
            'name' => 'John Doe',
            'phone' => 1234567890,
        ]);

        Tenancy::set((int) $biz->id);
        $this->assertEquals(0, ChatLead::where('chat_session_id', $session->id)->count());

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Bad Request']);
    }

    /**
     * Decision: The HTTP stack normalises a whitespace message to null.
     * Reasoning: A detail that is blank or whitespace was not given (R245). The framework's global middleware
     * (TrimStrings and ConvertEmptyStringsToNull) trims whitespace and converts empty strings to null before
     * they reach the controller. This test proves the HTTP path protects the action from receiving whitespace.
     */
    public function test_http_middleware_normalises_whitespace_message_to_null(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Lead Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $key = app(PixelKeys::class)->ensureFor($biz);

        $session = ChatSession::create([
            'business_id' => $biz->id,
            'session_token' => 'sess_lead_test',
            'status' => 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => false,
        ]);
        Tenancy::forgetAll();

        $response = $this->postJson("/api/chat/{$key}/capture", [
            'session_token' => 'sess_lead_test',
            'name' => 'John Doe',
            'phone' => '1234567890',
            'email' => 'john@example.com',
            'message' => "   \n\t ",
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['id']);

        Tenancy::set((int) $biz->id);
        $this->assertEquals(1, ChatLead::where('chat_session_id', $session->id)->count());
        $lead = ChatLead::first();
        $this->assertNull($lead->message);
    }

    /**
     * Decision: The HTTP stack normalises a whitespace phone to null, triggering a 400 before the action.
     * Reasoning: TrimStrings and ConvertEmptyStringsToNull convert whitespace to null. The controller's
     * !is_string($phone) check catches this and returns a 400 Bad Request. The DomainException in the action
     * is therefore unreachable over HTTP.
     */
    public function test_whitespace_capture_phone_returns_400(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Lead Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $key = app(PixelKeys::class)->ensureFor($biz);

        $session = ChatSession::create([
            'business_id' => $biz->id,
            'session_token' => 'sess_lead_test',
            'status' => 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => false,
        ]);
        Tenancy::forgetAll();

        $response = $this->postJson("/api/chat/{$key}/capture", [
            'session_token' => 'sess_lead_test',
            'name' => 'John Doe',
            'phone' => "   \n\t ",
        ]);

        Tenancy::set((int) $biz->id);
        $this->assertEquals(0, ChatLead::where('chat_session_id', $session->id)->count());

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Bad Request']);
    }

    public function test_capture_drops_message_when_consent_absent(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Lead Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $key = app(PixelKeys::class)->ensureFor($biz);

        $session = ChatSession::create([
            'business_id' => $biz->id,
            'session_token' => 'sess_lead_test_noconsent',
            'status' => 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => false,
        ]);
        Tenancy::forgetAll();

        $response = $this->postJson("/api/chat/{$key}/capture", [
            'session_token' => 'sess_lead_test_noconsent',
            'name' => 'John Doe',
            'phone' => '1234567890',
            'message' => 'Hello',
        ]);

        $response->assertStatus(201);
        Tenancy::set((int) $biz->id);
        $lead = ChatLead::where('chat_session_id', $session->id)->first();
        $this->assertNull($lead->message);
    }

    public function test_capture_keeps_message_when_consent_provided(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Lead Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $key = app(PixelKeys::class)->ensureFor($biz);

        $session = ChatSession::create([
            'business_id' => $biz->id,
            'session_token' => 'sess_lead_test_consent',
            'status' => 'active',
            'rage_clicks_count' => 0,
            'is_ai_capped' => false,
        ]);
        Tenancy::forgetAll();

        $response = $this->postJson("/api/chat/{$key}/capture", [
            'session_token' => 'sess_lead_test_consent',
            'name' => 'John Doe',
            'phone' => '1234567890',
            'message' => 'Hello',
            'consent' => true,
        ]);

        $response->assertStatus(201);
        Tenancy::set((int) $biz->id);
        $lead = ChatLead::where('chat_session_id', $session->id)->first();
        $this->assertEquals('Hello', $lead->message);
    }
}
