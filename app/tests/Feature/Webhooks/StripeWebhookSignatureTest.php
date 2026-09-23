<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;

it('accepts signed unhandled event', function () {
    Config::set('credentials.stripe_webhook_secret', 'whsec_test');

    $payload = json_encode([
        'id' => 'evt_12345',
        'type' => 'customer.created',
    ], JSON_THROW_ON_ERROR);

    $t = time();
    $sig = 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, 'whsec_test');

    $response = $this->call(
        'POST',
        '/webhooks/stripe',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $sig,
        ],
        $payload
    );

    $response->assertStatus(200);
    $response->assertExactJson(['handled' => 'ignored']);
});

it('refuses forged signature and writes nothing', function () {
    Config::set('credentials.stripe_webhook_secret', 'whsec_test');

    $payload = json_encode([
        'id' => 'evt_12346',
        'type' => 'customer.created',
    ], JSON_THROW_ON_ERROR);

    $t = time();
    $sig = 't='.$t.',v1=forged';

    $response = $this->call(
        'POST',
        '/webhooks/stripe',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $sig,
        ],
        $payload
    );

    $response->assertStatus(400);
    $response->assertExactJson(['error' => 'signature verification failed']);

    $this->assertDatabaseMissing('stripe_events', ['stripe_event_id' => 'evt_12346']);
});

it('reports duplicate on replayed identical signed event', function () {
    Config::set('credentials.stripe_webhook_secret', 'whsec_test');

    $payload = json_encode([
        'id' => 'evt_12347',
        'type' => 'customer.created',
    ], JSON_THROW_ON_ERROR);

    $t = time();
    $sig = 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$payload, 'whsec_test');

    $response1 = $this->call(
        'POST',
        '/webhooks/stripe',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $sig,
        ],
        $payload
    );

    $response1->assertStatus(200);
    $response1->assertExactJson(['handled' => 'ignored']);

    $response2 = $this->call(
        'POST',
        '/webhooks/stripe',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $sig,
        ],
        $payload
    );

    $response2->assertStatus(200);
    $response2->assertExactJson(['handled' => 'duplicate']);
});
