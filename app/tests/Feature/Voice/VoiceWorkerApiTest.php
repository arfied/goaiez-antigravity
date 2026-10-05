<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyVoiceWorker;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Testing\TestResponse;

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

test('with the receptionist switched on, call start says it is not built yet rather than pretending to answer', function (): void {
    config(['credentials.voice_worker_secret' => 'voice-secret-9101']);
    app(DefaultsRegistry::class)->set('voice.live_agent.enabled', true, 'test');
    $body = json_encode(['dialled_e164' => '+15125550101']);

    postVoice($body, signedVoiceHeaders($body))->assertStatus(503)->assertJson(['status' => 'unavailable']);
});
