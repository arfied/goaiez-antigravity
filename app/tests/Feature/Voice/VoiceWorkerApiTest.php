<?php

declare(strict_types=1);

use App\Enums\CallAnsweredBy;
use App\Enums\CallOutcome;
use App\Enums\LiveAnswerMode;
use App\Http\Controllers\Voice\Live\CallPriceToolController;
use App\Http\Middleware\VerifyVoiceWorker;
use App\Jobs\Voice\NotifyOwnerOfCallMessageJob;
use App\Models\AuditLogEntry;
use App\Models\Call;
use App\Models\VoiceUsageEvent;
use App\Modules\CAgent\Models\AgentRefusal;
use App\Modules\CAgent\Models\AgentTurn;
use App\Modules\X163\Models\PriceBookItem;
use App\Services\Agent\AgentRules;
use App\Services\Assistant\PriceBook;
use App\Services\Config\DefaultsRegistry;
use App\Services\Ops\PlatformHealth;
use App\Services\Ops\PlatformHealthChecks;
use App\Services\Sms\TenantNumbers;
use App\Services\Voice\CallForwarding;
use App\Services\Voice\Live\LiveCallTokens;
use App\Services\Voice\RecordingAnnouncement;
use App\Services\Voice\RecordingAnnouncementAttestation;
use App\Services\Voice\VoiceGreeting;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Queue;
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

function postVoiceTo(string $path, string $body, array $headers): TestResponse
{
    return test()->call('POST', $path, [], [], [], array_combine(
        array_map(fn (string $h): string => 'HTTP_'.strtoupper(str_replace('-', '_', $h)), array_keys($headers)),
        array_values($headers),
    ), $body);
}

/**
 * A call the receptionist has answered: [business id, call token].
 *
 * @return array{0: int, 1: string}
 */
function voiceLiveAnsweredCall(string $e164, string $transportCallId): array
{
    $businessId = voiceLiveTenant($e164);
    $body = json_encode(['dialled_e164' => $e164, 'from_e164' => '+14155550932', 'transport_call_id' => $transportCallId]);
    $token = (string) postVoice($body, signedVoiceHeaders($body))->assertOk()->json('call_token');

    return [$businessId, $token];
}

test('what was said on a call is recorded once per turn, however often the worker sends the batch', function (): void {
    [$businessId, $token] = voiceLiveAnsweredCall('+15555550942', 'SCL_9309');

    $first = json_encode(['turns' => [
        ['turn' => 1, 'caller' => 'How much is your callout fee?', 'agent' => 'The business will confirm the price and get back to you.', 'metrics' => ['llm_ttft_ms' => 180]],
        ['turn' => 2, 'caller' => 'Okay, thanks.', 'agent' => 'You are welcome.'],
    ]]);
    postVoiceTo("/api/voice/v1/calls/{$token}/turns", $first, signedVoiceHeaders($first))
        ->assertOk()->assertExactJson(['status' => 'recorded', 'written' => 2]);

    $retry = json_encode(['turns' => [
        ['turn' => 2, 'caller' => 'Okay, thanks.', 'agent' => 'You are welcome.'],
        ['turn' => 3, 'caller' => 'Bye.', 'agent' => 'Goodbye.'],
    ]]);
    postVoiceTo("/api/voice/v1/calls/{$token}/turns", $retry, signedVoiceHeaders($retry))
        ->assertOk()->assertExactJson(['status' => 'recorded', 'written' => 1]);

    $turns = Tenancy::actingAs($businessId, fn () => AgentTurn::query()->whereNotNull('call_id')->orderBy('turn_number')->get());
    expect($turns->pluck('turn_number')->all())->toBe([1, 2, 3])
        ->and($turns[0]->user_message)->toBe('How much is your callout fee?')
        ->and($turns[0]->metrics)->toBe(['llm_ttft_ms' => 180])
        ->and($turns[1]->metrics)->toBeNull();
});

test('a call token that opens to nothing, or to another business\'s call, is a 404 and writes nothing', function (): void {
    [$businessA] = voiceLiveAnsweredCall('+15555550943', 'SCL_9310');
    [$businessB] = voiceLiveAnsweredCall('+15555550944', 'SCL_9311');
    $callOfB = Tenancy::actingAs($businessB, fn (): int => (int) Call::query()->firstOrFail()->getKey());

    $body = json_encode(['turns' => [['turn' => 1, 'caller' => 'Hello', 'agent' => 'Hi']]]);
    postVoiceTo('/api/voice/v1/calls/not-a-token/turns', $body, signedVoiceHeaders($body, at: time() - 2))->assertNotFound();

    // A token that names business A and B's call: the call is not A's, so it is not found.
    $crossed = app(LiveCallTokens::class)->mint($businessA, $callOfB);
    postVoiceTo("/api/voice/v1/calls/{$crossed}/turns", $body, signedVoiceHeaders($body, at: time() - 1))->assertNotFound();
    postVoiceTo("/api/voice/v1/calls/{$crossed}/end", '{}', signedVoiceHeaders('{}'))->assertNotFound();

    expect(Tenancy::actingAs($businessA, fn (): int => AgentTurn::query()->count()))->toBe(0)
        ->and(Tenancy::actingAs($businessB, fn (): int => AgentTurn::query()->count()))->toBe(0)
        ->and(Tenancy::actingAs($businessB, fn () => Call::query()->firstOrFail()->outcome))->toBe(CallOutcome::InProgress);
});

test('ending a call settles it as answered and puts its minutes on the voice meter once', function (): void {
    [$businessId, $token] = voiceLiveAnsweredCall('+15555550945', 'SCL_9312');
    $this->travel(90)->seconds();

    postVoiceTo("/api/voice/v1/calls/{$token}/end", '{}', signedVoiceHeaders('{}', at: time() - 1))
        ->assertOk()->assertExactJson(['status' => 'ended']);
    postVoiceTo("/api/voice/v1/calls/{$token}/end", '{}', signedVoiceHeaders('{}'))
        ->assertOk()->assertExactJson(['status' => 'ended']);

    $call = Tenancy::actingAs($businessId, fn (): Call => Call::query()->where('provider_call_id', 'livekit:SCL_9312')->firstOrFail());
    expect($call->outcome)->toBe(CallOutcome::Answered)
        ->and($call->ended_at)->not->toBeNull();

    $meter = VoiceUsageEvent::query()->where('provider_call_id', 'livekit:SCL_9312')->get();
    expect($meter)->toHaveCount(1)
        ->and($meter[0]->business_id)->toBe($businessId)
        ->and($meter[0]->billable_seconds)->toBe(90);
});

test('a turns batch that is empty, too long or malformed is refused as invalid and writes nothing', function (): void {
    [$businessId, $token] = voiceLiveAnsweredCall('+15555550946', 'SCL_9313');

    foreach ([
        ['turns' => []],
        ['turns' => [['turn' => 0, 'caller' => 'a', 'agent' => 'b']]],
        ['turns' => [['turn' => 1, 'caller' => 'a']]],
        ['turns' => [['turn' => 1, 'caller' => 'a', 'agent' => 'b', 'metrics' => ['Bad Key' => 1]]]],
        ['turns' => array_fill(0, 51, ['turn' => 1, 'caller' => 'a', 'agent' => 'b'])],
    ] as $i => $payload) {
        $body = json_encode($payload);
        postVoiceTo("/api/voice/v1/calls/{$token}/turns", $body, signedVoiceHeaders($body, at: time() - $i))
            ->assertStatus(422)->assertJson(['status' => 'invalid']);
    }

    expect(Tenancy::actingAs($businessId, fn (): int => AgentTurn::query()->count()))->toBe(0);
});

test('a caller asking a price nobody has confirmed is refused with NO_FACT, and the refusal names the call', function (): void {
    [$businessId, $token] = voiceLiveAnsweredCall('+15555550947', 'SCL_9314');
    $body = json_encode(['question' => 'How much is your callout fee?']);

    postVoiceTo("/api/voice/v1/calls/{$token}/tools/price", $body, signedVoiceHeaders($body))
        ->assertOk()
        ->assertExactJson(['status' => 'refused', 'refusal_code' => 'NO_FACT', 'say' => CallPriceToolController::NO_PRICE_WORDS]);

    $refusal = Tenancy::actingAs($businessId, fn (): AgentRefusal => AgentRefusal::query()->sole());
    $callId = Tenancy::actingAs($businessId, fn (): int => (int) Call::query()->where('provider_call_id', 'livekit:SCL_9314')->value('id'));
    expect($refusal->refusal_code)->toBe('NO_FACT')
        ->and((int) $refusal->call_id)->toBe($callId)
        ->and($refusal->user_input)->toBe('How much is your callout fee?');
});

test('a confirmed price is given with how to say it and the business\'s price line, and nothing is refused', function (): void {
    [$businessId, $token] = voiceLiveAnsweredCall('+15555550948', 'SCL_9315');
    // PriceBook::set() is the owner typing a price, which confirms it on the spot.
    Tenancy::actingAs($businessId, fn () => app(PriceBook::class)->set('Callout fee', 8500));
    $disclaimer = Tenancy::actingAs($businessId, fn (): string => app(PriceBook::class)->disclaimer());
    $body = json_encode(['question' => 'How much is your callout fee?']);

    postVoiceTo("/api/voice/v1/calls/{$token}/tools/price", $body, signedVoiceHeaders($body))
        ->assertOk()
        ->assertExactJson([
            'status' => 'price',
            'service' => 'Callout fee',
            'amount_cents' => 8500,
            'currency' => 'USD',
            'spoken' => '$85.00',
            'disclaimer' => $disclaimer,
        ]);

    expect(Tenancy::actingAs($businessId, fn (): int => AgentRefusal::query()->count()))->toBe(0);
});

test('a price nobody has confirmed is never given on a call — UNCONFIRMED is refused and recorded', function (): void {
    [$businessId, $token] = voiceLiveAnsweredCall('+15555550949', 'SCL_9316');
    Tenancy::actingAs($businessId, function (): void {
        app(PriceBook::class)->set('Callout fee', 8500);
        PriceBookItem::query()->where('service_name', 'Callout fee')->update(['is_confirmed' => false, 'confirmed_at' => null]);
    });
    $body = json_encode(['question' => 'How much is your callout fee?']);

    $response = postVoiceTo("/api/voice/v1/calls/{$token}/tools/price", $body, signedVoiceHeaders($body))
        ->assertOk()
        ->assertJson(['status' => 'refused', 'refusal_code' => 'UNCONFIRMED']);

    expect($response->getContent())->not->toContain('8500')->not->toContain('85.00')
        ->and(Tenancy::actingAs($businessId, fn (): int => AgentRefusal::query()->where('refusal_code', 'UNCONFIRMED')->count()))->toBe(1);
});

test('the price tool refuses a missing question and a token that opens to nothing', function (): void {
    [, $token] = voiceLiveAnsweredCall('+15555550950', 'SCL_9317');

    postVoiceTo("/api/voice/v1/calls/{$token}/tools/price", '{}', signedVoiceHeaders('{}'))
        ->assertStatus(422)->assertJson(['status' => 'invalid']);

    $body = json_encode(['question' => 'How much is your callout fee?']);
    postVoiceTo('/api/voice/v1/calls/not-a-token/tools/price', $body, signedVoiceHeaders($body))->assertNotFound();
});

test('a message the caller leaves is kept on the call, the first one stands, and the owner is told once', function (): void {
    [$businessId, $token] = voiceLiveAnsweredCall('+15555550951', 'SCL_9318');
    Queue::fake();

    $first = json_encode(['message' => 'Leak under the sink 9431, please ring back today.', 'name' => 'Dana 9432', 'callback' => '+1 415 555 0999']);
    postVoiceTo("/api/voice/v1/calls/{$token}/message", $first, signedVoiceHeaders($first))
        ->assertOk()->assertExactJson(['status' => 'taken']);

    $second = json_encode(['message' => 'Actually never mind 9433.']);
    postVoiceTo("/api/voice/v1/calls/{$token}/message", $second, signedVoiceHeaders($second))
        ->assertOk()->assertExactJson(['status' => 'taken']);

    $call = Tenancy::actingAs($businessId, fn (): Call => Call::query()->where('provider_call_id', 'livekit:SCL_9318')->firstOrFail());
    expect($call->message_text)->toBe('Leak under the sink 9431, please ring back today.')
        ->and($call->message_name)->toBe('Dana 9432')
        ->and($call->message_callback)->toBe('+1 415 555 0999')
        ->and($call->message_left_at)->not->toBeNull();

    Queue::assertPushed(NotifyOwnerOfCallMessageJob::class, 1);
    Queue::assertPushed(NotifyOwnerOfCallMessageJob::class, fn (NotifyOwnerOfCallMessageJob $job): bool => $job->providerCallId === 'livekit:SCL_9318');
});

test('a message with no words, or a callback that is not a phone number, is refused; a token that opens to nothing is a 404', function (): void {
    [$businessId, $token] = voiceLiveAnsweredCall('+15555550952', 'SCL_9319');

    foreach ([['message' => '   '], ['message' => 'Hi', 'callback' => 'ring me maybe']] as $i => $payload) {
        $body = json_encode($payload);
        postVoiceTo("/api/voice/v1/calls/{$token}/message", $body, signedVoiceHeaders($body, at: time() - $i))
            ->assertStatus(422)->assertJson(['status' => 'invalid']);
    }

    $body = json_encode(['message' => 'Hello']);
    postVoiceTo('/api/voice/v1/calls/not-a-token/message', $body, signedVoiceHeaders($body))->assertNotFound();

    expect(Tenancy::actingAs($businessId, fn () => Call::query()->firstOrFail()->message_text))->toBeNull();
});

test('a signed heartbeat from the voice worker is recorded as a beat, and an unsigned one is refused', function (): void {
    config(['credentials.voice_worker_secret' => 'voice-secret-9101']);
    expect(app(PlatformHealth::class)->lastBeat(PlatformHealthChecks::VOICE_WORKER))->toBeNull();

    postVoiceTo('/api/voice/v1/heartbeat', '{}', ['Content-Type' => 'application/json'])->assertUnauthorized();
    expect(app(PlatformHealth::class)->lastBeat(PlatformHealthChecks::VOICE_WORKER))->toBeNull();

    postVoiceTo('/api/voice/v1/heartbeat', '{}', signedVoiceHeaders('{}'))->assertOk()->assertExactJson(['status' => 'ok']);
    expect(app(PlatformHealth::class)->lastBeat(PlatformHealthChecks::VOICE_WORKER))->not->toBeNull();
});
