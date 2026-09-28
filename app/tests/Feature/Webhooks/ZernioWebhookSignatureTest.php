<?php

declare(strict_types=1);

use App\Models\ZernioWebhookEvent;
use Illuminate\Support\Facades\Config;

it('accepts a correctly signed payload', function () {
    Config::set('credentials.zernio_webhook_secret', 'test_secret');

    $payload = json_encode([
        'id' => 'evt_123',
        'event' => 'ping',
    ], JSON_THROW_ON_ERROR);

    $sig = hash_hmac('sha256', $payload, 'test_secret');

    $response = $this->call(
        'POST',
        '/webhooks/zernio',
        [], [], [],
        ['HTTP_X_ZERNIO_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $payload,
    );

    $response->assertStatus(200);
    expect($response->json('ok'))->toBeTrue();
});

it('refuses a payload whose signature does not match', function () {
    Config::set('credentials.zernio_webhook_secret', 'test_secret');

    $payload = json_encode([
        'id' => 'evt_123',
        'event' => 'ping',
    ], JSON_THROW_ON_ERROR);

    $sig = hash_hmac('sha256', $payload, 'wrong_secret');

    $response = $this->call(
        'POST',
        '/webhooks/zernio',
        [], [], [],
        ['HTTP_X_ZERNIO_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $payload,
    );

    $response->assertStatus(401);
    expect($response->json('error'))->toBe('unverified');

    $events = ZernioWebhookEvent::count();
    expect($events)->toBe(0);
});

it('refuses a signed but empty payload', function () {
    Config::set('credentials.zernio_webhook_secret', 'test_secret');

    $payload = '';

    $sig = hash_hmac('sha256', $payload, 'test_secret');

    $response = $this->call(
        'POST',
        '/webhooks/zernio',
        [], [], [],
        ['HTTP_X_ZERNIO_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $payload,
    );

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('unreadable payload');
});
