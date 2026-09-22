<?php

declare(strict_types=1);

use App\Enums\WebhookVerification;
use App\Services\Mail\SnsMessageVerifier;

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

it('holds a subscription confirmation for an operator when verification is stubbed', function () {
    $verifier = Mockery::mock(SnsMessageVerifier::class);
    $verifier->shouldReceive('verify')->andReturn(WebhookVerification::Verified);
    $this->instance(SnsMessageVerifier::class, $verifier);

    $payload = json_encode([
        'Type' => 'SubscriptionConfirmation',
        'MessageId' => '123',
        'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:MyTopic',
        'Token' => 'tok_123',
        'Message' => 'test',
        'SubscribeURL' => 'https://sns.us-east-1.amazonaws.com/?Action=ConfirmSubscription',
        'Timestamp' => '2023-01-01T00:00:00Z',
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/webhooks/ses',
        [], [], [],
        ['CONTENT_TYPE' => 'text/plain'],
        $payload
    );

    $response->assertStatus(200);
    expect($response->json('status'))->toBe('confirmation required');
});

it('ignores a non-Notification type when verification is stubbed', function () {
    $verifier = Mockery::mock(SnsMessageVerifier::class);
    $verifier->shouldReceive('verify')->andReturn(WebhookVerification::Verified);
    $this->instance(SnsMessageVerifier::class, $verifier);

    $payload = json_encode([
        'Type' => 'UnsubscribeConfirmation',
        'MessageId' => '123',
        'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:MyTopic',
        'Token' => 'tok_123',
        'Message' => 'test',
        'SubscribeURL' => 'https://sns.us-east-1.amazonaws.com/?Action=Unsubscribe',
        'Timestamp' => '2023-01-01T00:00:00Z',
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/webhooks/ses',
        [], [], [],
        ['CONTENT_TYPE' => 'text/plain'],
        $payload
    );

    $response->assertStatus(200);
    expect($response->json('status'))->toBe('ignored');
});
