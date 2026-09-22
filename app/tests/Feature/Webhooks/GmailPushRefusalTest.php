<?php

declare(strict_types=1);

beforeEach(function () {
    config([
        'platform_mail.gmail.push.audience' => 'test-audience',
        'platform_mail.gmail.push.service_account' => 'test@example.com',
    ]);
});

it('refuses a push with no Authorization header', function () {
    $payload = json_encode([
        'message' => [
            'data' => base64_encode(json_encode(['emailAddress' => 'a@b.com', 'historyId' => '1'])),
            'messageId' => '1',
        ],
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/webhooks/gmail',
        [], [], [],
        ['CONTENT_TYPE' => 'application/json'],
        $payload
    );

    $response->assertStatus(401);
    expect($response->json())->toBe(['error' => 'unverified']);
});

it('refuses a push whose bearer token is not a Google token', function () {
    $payload = json_encode([
        'message' => [
            'data' => base64_encode(json_encode(['emailAddress' => 'a@b.com', 'historyId' => '1'])),
            'messageId' => '1',
        ],
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/webhooks/gmail',
        [], [], [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer not.a.jwt',
        ],
        $payload
    );

    $response->assertStatus(401);
    expect($response->json())->toBe(['error' => 'unverified']);
});
