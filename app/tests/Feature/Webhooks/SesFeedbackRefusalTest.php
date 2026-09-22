<?php

declare(strict_types=1);

it('refuses a body that is not an SNS envelope', function () {
    $response = $this->call(
        'POST',
        '/webhooks/ses',
        [], [], [],
        ['CONTENT_TYPE' => 'text/plain'],
        'not a json envelope'
    );

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('unreadable payload');
});

it('refuses an envelope that does not verify', function () {
    $payload = json_encode([
        'Type' => 'Notification',
        'MessageId' => '123',
        'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:MyTopic',
        'Message' => 'test',
        'Timestamp' => '2023-01-01T00:00:00Z',
        'SignatureVersion' => '1',
        'Signature' => 'invalid',
        'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-123.pem',
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/webhooks/ses',
        [], [], [],
        ['CONTENT_TYPE' => 'text/plain'],
        $payload
    );

    $response->assertStatus(401);
    expect($response->json('error'))->toBe('unverified');
});
