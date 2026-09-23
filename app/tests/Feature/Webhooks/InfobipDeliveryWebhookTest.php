<?php

declare(strict_types=1);

use App\Enums\CredentialEnvironment;
use App\Services\Config\CredentialStore;

it('accepts signed delivery receipt', function () {
    app(CredentialStore::class)->set(
        'infobip_webhook_secret',
        'test-secret',
        'system',
        CredentialEnvironment::Live
    );
    config(['services.infobip.signature_header' => 'X-Hub-Signature']);
    config(['services.infobip.signature_scheme' => 'body']);

    $body = json_encode([
        'results' => [
            [
                'messageId' => '1234567890',
                'status' => [
                    'groupName' => 'DELIVERED',
                ],
                'callbackData' => 'ref-1',
                'error' => [
                    'name' => 'NO_ERROR',
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $hex = hash_hmac('sha256', $body, 'test-secret');

    $response = $this->call(
        'POST',
        '/webhooks/infobip/delivery',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE' => 'SHA256='.$hex,
        ],
        $body
    );

    $response->assertStatus(200);
});

it('refuses forged signature', function () {
    app(CredentialStore::class)->set(
        'infobip_webhook_secret',
        'test-secret',
        'system',
        CredentialEnvironment::Live
    );
    config(['services.infobip.signature_header' => 'X-Hub-Signature']);
    config(['services.infobip.signature_scheme' => 'body']);

    $body = json_encode([
        'results' => [
            [
                'messageId' => '1234567890',
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/webhooks/infobip/delivery',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE' => 'SHA256=forged',
        ],
        $body
    );

    $response->assertStatus(401);
    $response->assertExactJson(['error' => 'unverified']);
});

it('refuses unreadable payload', function () {
    app(CredentialStore::class)->set(
        'infobip_webhook_secret',
        'test-secret',
        'system',
        CredentialEnvironment::Live
    );
    config(['services.infobip.signature_header' => 'X-Hub-Signature']);
    config(['services.infobip.signature_scheme' => 'body']);

    $body = json_encode([
        'results' => 'not an array',
    ], JSON_THROW_ON_ERROR);

    $hex = hash_hmac('sha256', $body, 'test-secret');

    $response = $this->call(
        'POST',
        '/webhooks/infobip/delivery',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE' => 'SHA256='.$hex,
        ],
        $body
    );

    $response->assertStatus(422);
    $response->assertExactJson(['error' => 'unreadable payload']);
});
