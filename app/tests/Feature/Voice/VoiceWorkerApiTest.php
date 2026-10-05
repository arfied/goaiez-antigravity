<?php

declare(strict_types=1);

use App\Enums\CallAnsweredBy;
use App\Enums\CallOutcome;
use App\Enums\LiveAnswerMode;
use App\Http\Middleware\VerifyVoiceWorker;
use App\Models\AuditLogEntry;
use App\Models\Call;
use App\Services\Agent\AgentRules;
use App\Services\Config\DefaultsRegistry;
use App\Services\Sms\TenantNumbers;
use App\Services\Voice\CallForwarding;
use App\Services\Voice\Live\LiveCallTokens;
use App\Services\Voice\RecordingAnnouncement;
use App\Services\Voice\RecordingAnnouncementAttestation;
use App\Services\Voice\VoiceGreeting;
use App\Support\Tenancy;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
 * The voice brain API refuses anything the voice worker did not sign (AI receptionist plan, 2026-10-05).
 */

function signedVoiceHeaders(string $body, string $secret = 'voice-secret-9101', ?int $at = null): array
{
    $timestamp = (string) ($at ?? time());

    return [
        VerifyVoiceWorker::TIMESTAMP_HEADER => $timestamp,
        VerifyVoiceWorker::SIGNATURE_HEADER => hash_hmac('sha256', $timestamp.'.'.$body, $secret),
        'Content-Type' => 'application/json',
    ];
}

function postVoice(string $body, array $headers): TestResponse
{
    return test()->call('POST', '/api/voice/v1/calls', [], [], [], array_combine(
        array_map(fn (string $h): string => 'HTTP_'.strtoupper(str_replace('-', '_', $h)), array_keys($headers)),
        array_values($headers),
    ), $body);
}

test('a signed request from the voice worker is told the receptionist is off, and a replay of it is refused', function (): void {
    config(['credentials.voice_worker_secret' => 'voice-secret-9101']);
    $body = json_encode(['dialled_e164' => '+15125550101', 'from_e164' => '+15125550102']);
    $headers = signedVoiceHeaders($body);

    postVoice($body, $headers)->assertOk()->assertExactJson(['status' => 'disabled', 'reason' => 'voice.live_agent.enabled is off']);
    postVoice($body, $headers)->assertUnauthorized();
});

test('an unsigned, mis-signed, stale or keyless request is refused', function (): void {
    $body = json_encode(['dialled_e164' => '+15125550101']);

    // No key set on this deployment: refused even when signed.
    postVoice($body, signedVoiceHeaders($body))->assertUnauthorized();

    config(['credentials.voice_worker_secret' => 'voice-secret-9101']);
    postVoice($body, ['Content-Type' => 'application/json'])->assertUnauthorized();
    postVoice($body, signedVoiceHeaders($body, 'not-the-secret'))->assertUnauthorized();
    postVoice($body, signedVoiceHeaders($body, at: time() - 3600))->assertUnauthorized();
    // The signature covers the body: a body changed after signing is refused.
    postVoice(json_encode(['dialled_e164' => '+15125559999']), signedVoiceHeaders($body))->assertUnauthorized();
});

/**
 * A business holding $e164, the receptionist switched on and the announcement attested — and no tenant left ambient, as the
 * worker's request arrives with none. Number fixture taken from
 * VoiceCallIngestTest::test_one_inbound_number_produces_one_conversation_and_never_a_second_person; attestation from
 * PlatformSettingsAdminTest 'turning it on goes through once an operator has attested'.
 */
function voiceLiveTenant(string $e164): int
{
    config(['credentials.voice_worker_secret' => 'voice-secret-9101']);
    app(DefaultsRegistry::class)->set('voice.live_agent.enabled', true, 'test');
    app(RecordingAnnouncement::class)->attest(new RecordingAnnouncementAttestation(
        statementVersion: RecordingAnnouncement::STATEMENT_VERSION,
        attestedBy: 'support:1',
        announcementMediaId: 'announce-8k-ulaw.wav',
        proof: ['channel' => 'console'],
    ));

    $business = TestCase::provisionTenant(['name' => 'Harbor Plumbing 9301']);
    app(TenantNumbers::class)->releaseFromTenant($business->id);
    $number = app(TenantNumbers::class)->assign($business->id, $e164);
    app(TenantNumbers::class)->bringIntoService($number, 'test');
    Tenancy::forgetAll();

    return (int) $business->id;
}

function voiceLiveCallCount(int $businessId): int
{
    return Tenancy::actingAs($businessId, fn (): int => Call::query()->count());
}

test('a call to a business number is answered by the AI receptionist, the announcement first and then an AI disclosure', function (): void {
    $businessId = voiceLiveTenant('+15555550931');
    $body = json_encode(['dialled_e164' => '+15555550931', 'from_e164' => '+14155550932', 'transport_call_id' => 'SCL_9301']);

    $response = postVoice($body, signedVoiceHeaders($body))
        ->assertOk()
        ->assertJson(['status' => 'answer', 'announcement' => VoiceGreeting::ANNOUNCEMENT]);

    expect($response->json('disclosure'))->toContain('Harbor Plumbing 9301')->toContain('AI assistant');

    $claim = app(LiveCallTokens::class)->open((string) $response->json('call_token'));
    expect($claim)->not->toBeNull()
        ->and($claim?->businessId)->toBe($businessId);

    $call = Tenancy::actingAs($businessId, fn (): Call => Call::query()->where('provider_call_id', 'livekit:SCL_9301')->firstOrFail());
    expect($claim?->callId)->toBe($call->id)
        ->and($call->answered_by)->toBe(CallAnsweredBy::Agent)
        ->and($call->outcome)->toBe(CallOutcome::InProgress)
        ->and($call->from_e164)->toBe('+14155550932')
        ->and($call->to_e164)->toBe('+15555550931')
        ->and($call->answered_at)->not->toBeNull()
        ->and(voiceLiveCallCount($businessId))->toBe(1);
});

test('a retried call start answers the same call, writes no second row, and does not ask the gates again', function (): void {
    $businessId = voiceLiveTenant('+15555550933');
    $body = json_encode(['dialled_e164' => '+15555550933', 'from_e164' => '+14155550932', 'transport_call_id' => 'SCL_9302']);

    $first = postVoice($body, signedVoiceHeaders($body, at: time() - 1))->assertOk()->assertJson(['status' => 'answer']);

    // The spend ceiling closes after the caller was picked up: the retry is for a call already answered.
    app(DefaultsRegistry::class)->set('ai.monthly_cap_per_tenant', 0, 'test');
    $second = postVoice($body, signedVoiceHeaders($body))->assertOk()->assertJson(['status' => 'answer']);

    $tokens = app(LiveCallTokens::class);
    expect($tokens->open((string) $second->json('call_token'))?->callId)
        ->toBe($tokens->open((string) $first->json('call_token'))?->callId)
        ->and(voiceLiveCallCount($businessId))->toBe(1);
});

test('a call to a number nobody owns is declined', function (): void {
    voiceLiveTenant('+15555550934');
    $body = json_encode(['dialled_e164' => '+15555550939', 'from_e164' => '+14155550932', 'transport_call_id' => 'SCL_9303']);

    postVoice($body, signedVoiceHeaders($body))->assertOk()->assertExactJson(['status' => 'declined', 'reason' => 'unknown_number']);
});

test('with no attested recording announcement the call is declined and nothing is written', function (): void {
    $businessId = voiceLiveTenant('+15555550935');
    app(RecordingAnnouncement::class)->revoke('test');
    $body = json_encode(['dialled_e164' => '+15555550935', 'from_e164' => '+14155550932', 'transport_call_id' => 'SCL_9304']);

    postVoice($body, signedVoiceHeaders($body))->assertOk()->assertExactJson(['status' => 'declined', 'reason' => 'announcement_not_attested']);
    expect(voiceLiveCallCount($businessId))->toBe(0);
});

test('a business that chose to be rung first is declined until ringing the owner is built, and nothing is written', function (): void {
    $businessId = voiceLiveTenant('+15555550936');
    Tenancy::actingAs($businessId, fn () => app(CallForwarding::class)->chooseLiveAnswer(LiveAnswerMode::OwnerFirst, 'user:1'));
    $body = json_encode(['dialled_e164' => '+15555550936', 'from_e164' => '+14155550932', 'transport_call_id' => 'SCL_9305']);

    postVoice($body, signedVoiceHeaders($body))->assertOk()->assertExactJson(['status' => 'declined', 'reason' => 'owner_first_not_built']);
    expect(voiceLiveCallCount($businessId))->toBe(0);
});

test('a business whose AI spend is refused is declined, and nothing is written', function (): void {
    $businessId = voiceLiveTenant('+15555550937');
    // Fixture taken from X220Test::test_c2c_budget_refused: a cap of zero refuses a never-funded account.
    app(DefaultsRegistry::class)->set('ai.monthly_cap_per_tenant', 0, 'test');
    $body = json_encode(['dialled_e164' => '+15555550937', 'from_e164' => '+14155550932', 'transport_call_id' => 'SCL_9306']);

    postVoice($body, signedVoiceHeaders($body))->assertOk()->assertExactJson(['status' => 'declined', 'reason' => 'ai_spend_refused']);
    expect(voiceLiveCallCount($businessId))->toBe(0);
});

test('a business past its daily inbound minutes is declined, and nothing is written', function (): void {
    $businessId = voiceLiveTenant('+15555550938');
    app(DefaultsRegistry::class)->set('voice.tenant_daily_inbound_minutes_ceiling', 0, 'test');
    $body = json_encode(['dialled_e164' => '+15555550938', 'from_e164' => '+14155550932', 'transport_call_id' => 'SCL_9307']);

    postVoice($body, signedVoiceHeaders($body))->assertOk()->assertExactJson(['status' => 'declined', 'reason' => 'daily_minutes_ceiling']);
    expect(voiceLiveCallCount($businessId))->toBe(0);
});

test('a call start without the worker call id is refused as invalid, and nothing is written', function (): void {
    $businessId = voiceLiveTenant('+15555550940');
    $body = json_encode(['dialled_e164' => '+15555550940', 'from_e164' => '+14155550932']);

    postVoice($body, signedVoiceHeaders($body))->assertStatus(422)->assertJson(['status' => 'invalid']);
    expect(voiceLiveCallCount($businessId))->toBe(0);
});

test('a business that has not chosen is answered AI-first, and choosing ring-me-first is recorded with both sides', function (): void {
    $business = TestCase::provisionTenant(['name' => 'Mode Tenant 9302']);
    Tenancy::set((int) $business->id);

    expect(app(CallForwarding::class)->liveAnswerFor())->toBe(LiveAnswerMode::AiFirst);

    app(CallForwarding::class)->chooseLiveAnswer(LiveAnswerMode::OwnerFirst, 'user:1');

    expect(app(CallForwarding::class)->liveAnswerFor())->toBe(LiveAnswerMode::OwnerFirst);
    $entry = AuditLogEntry::where('action', 'call_routing.live_answer_set')->sole();
    expect($entry->metadata['before']['live_answer_mode'])->toBe('ai_first')
        ->and($entry->metadata['after']['live_answer_mode'])->toBe('owner_first')
        ->and($entry->actor)->toBe('user:1');
});

test('a call token that was edited, is empty or has expired opens to nothing', function (): void {
    $tokens = app(LiveCallTokens::class);
    $token = $tokens->mint(7, 11);

    expect($tokens->open($token)?->callId)->toBe(11)
        ->and($tokens->open($token)?->businessId)->toBe(7)
        ->and($tokens->open(substr($token, 0, -4).'AAAA'))->toBeNull()
        ->and($tokens->open(''))->toBeNull();

    $this->travel(LiveCallTokens::LIFETIME_SECONDS + 1)->seconds();
    expect($tokens->open($token))->toBeNull();
});

test('call start hands the worker the receptionist instructions and the business name as a separate fact, and no price list', function (): void {
    voiceLiveTenant('+15555550941');
    $body = json_encode(['dialled_e164' => '+15555550941', 'from_e164' => '+14155550932', 'transport_call_id' => 'SCL_9308']);

    $response = postVoice($body, signedVoiceHeaders($body))->assertOk()->assertJson(['status' => 'answer']);

    expect($response->json('instructions'))->toBe(AgentRules::forVoiceCall())
        ->and($response->json('facts'))->toBe(['business_name' => 'Harbor Plumbing 9301'])
        ->and($response->getContent())->not->toContain('price list');
});
