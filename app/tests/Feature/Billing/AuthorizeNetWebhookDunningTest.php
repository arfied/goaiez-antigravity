<?php

declare(strict_types=1);

use App\Models\AuthorizeNetCustomer;
use App\Models\Business;
use App\Models\DunningAttempt;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

it('opens dunning when a signed subscription-failed notification arrives', function () {
    Mail::fake();
    Notification::fake();

    $user = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $user->id]);

    AuthorizeNetCustomer::create([
        'authorize_net_customer_profile_id' => 'cust_123',
        'authorize_net_subscription_id' => 'sub_123',
        'business_id' => $business->id,
    ]);

    Config::set('credentials.authorize_net_signature_key', 'test_sig');

    $payload = json_encode([
        'notificationId' => 'notif-1',
        'eventType' => 'net.authorize.customer.subscription.failed',
        'payload' => ['id' => 'sub_123'],
    ], JSON_THROW_ON_ERROR);

    $sig = hash_hmac('sha512', $payload, 'test_sig');

    Tenancy::forget();

    $response = $this->call(
        'POST',
        '/webhooks/authorize-net',
        [], [], [],
        ['HTTP_X_ANET_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $payload,
    );

    $response->assertStatus(200);

    Tenancy::setUser($user->id);
    Tenancy::set((int) $business->id);

    $attempts = DunningAttempt::where('business_id', $business->id)->get();

    expect($attempts)->toHaveCount(1);
    expect($attempts[0]->sequence)->toBe(1);
    expect($attempts[0]->attempt)->toBe(1);
    expect($attempts[0]->outcome->value)->toBe('declined');

    Tenancy::forget();
});

it('refuses an unsigned notification and writes nothing', function () {
    Mail::fake();
    Notification::fake();

    $user = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $user->id]);

    AuthorizeNetCustomer::create([
        'authorize_net_customer_profile_id' => 'cust_123',
        'authorize_net_subscription_id' => 'sub_123',
        'business_id' => $business->id,
    ]);

    Config::set('credentials.authorize_net_signature_key', 'test_sig');

    $payload = json_encode([
        'notificationId' => 'notif-1',
        'eventType' => 'net.authorize.customer.subscription.failed',
        'payload' => ['id' => 'sub_123'],
    ], JSON_THROW_ON_ERROR);

    $sig = hash_hmac('sha512', $payload, 'wrong_sig');

    Tenancy::forget();

    $response = $this->call(
        'POST',
        '/webhooks/authorize-net',
        [], [], [],
        ['HTTP_X_ANET_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $payload,
    );

    $response->assertStatus(400);

    Tenancy::setUser($user->id);
    Tenancy::set((int) $business->id);

    $attempts = DunningAttempt::where('business_id', $business->id)->count();

    expect($attempts)->toBe(0);

    Tenancy::forget();
});

it('writes one attempt when the same notification is delivered twice', function () {
    Mail::fake();
    Notification::fake();

    $user = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $user->id]);

    AuthorizeNetCustomer::create([
        'authorize_net_customer_profile_id' => 'cust_123',
        'authorize_net_subscription_id' => 'sub_123',
        'business_id' => $business->id,
    ]);

    Config::set('credentials.authorize_net_signature_key', 'test_sig');

    $payload = json_encode([
        'notificationId' => 'notif-1',
        'eventType' => 'net.authorize.customer.subscription.failed',
        'payload' => ['id' => 'sub_123'],
    ], JSON_THROW_ON_ERROR);

    $sig = hash_hmac('sha512', $payload, 'test_sig');

    Tenancy::forget();

    $response1 = $this->call(
        'POST',
        '/webhooks/authorize-net',
        [], [], [],
        ['HTTP_X_ANET_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $payload,
    );
    $response1->assertStatus(200);

    $response2 = $this->call(
        'POST',
        '/webhooks/authorize-net',
        [], [], [],
        ['HTTP_X_ANET_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $payload,
    );

    expect($response2->json('handled'))->toBe('duplicate');

    Tenancy::setUser($user->id);
    Tenancy::set((int) $business->id);

    $attempts = DunningAttempt::where('business_id', $business->id)->count();

    expect($attempts)->toBe(1);

    Tenancy::forget();
});
