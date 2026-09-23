<?php

declare(strict_types=1);

use App\Enums\BillingTerm;
use App\Enums\PaymentGateway;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\ImpersonationSession;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Mail::fake();
    Notification::fake();
});

it('requires confirm to be accepted', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $owner->id]);

    Subscription::factory()->create([
        'business_id' => $business->id,
        'gateway' => PaymentGateway::AuthorizeNet,
        'authorize_net_subscription_id' => 'sub_123',
        'stripe_customer_id' => null,
        'stripe_subscription_id' => null,
        'status' => SubscriptionStatus::Active,
        'term' => BillingTerm::Monthly,
    ]);

    Http::fake();

    $response = $this->actingAs($owner)
        ->withSession(['tenant_id' => $business->id])
        ->post(route('account.plan.cancel'));

    $response->assertInvalid(['confirm']);
    Http::assertNothingSent();
});

it('reports nothing to cancel if there is no live subscription', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $owner->id]);

    Http::fake();

    $response = $this->actingAs($owner)
        ->withSession(['tenant_id' => $business->id])
        ->post(route('account.plan.cancel'), ['confirm' => 'on']);

    $response->assertRedirect();

    $toasts = session('toasts');
    expect($toasts)->toBeArray()
        ->and($toasts[0]['message'])->toBe('There is no paid plan on this account, so nothing is being charged')
        ->and($toasts[0]['type'])->toBe('success');

    Http::assertNothingSent();
});

it('cancels an active subscription at the vendor and records it', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $owner->id]);

    $subscription = Subscription::factory()->create([
        'business_id' => $business->id,
        'gateway' => PaymentGateway::AuthorizeNet,
        'authorize_net_subscription_id' => 'sub_123',
        'stripe_customer_id' => null,
        'stripe_subscription_id' => null,
        'status' => SubscriptionStatus::Active,
        'term' => BillingTerm::Monthly,
    ]);

    Http::fake([
        '*request.api' => function ($request) {
            $body = json_decode($request->body(), true);
            $rootKey = array_key_first($body) ?? '';

            if ($rootKey === 'ARBGetSubscriptionStatusRequest') {
                return Http::response([
                    'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001']]],
                    'status' => 'active',
                ]);
            }
            if ($rootKey === 'ARBCancelSubscriptionRequest') {
                return Http::response([
                    'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001']]],
                ]);
            }

            return Http::response([], 404);
        },
    ]);

    $response = $this->actingAs($owner)
        ->withSession(['tenant_id' => $business->id])
        ->post(route('account.plan.cancel'), ['confirm' => 'on']);

    $response->assertRedirect();
    $toasts = session('toasts');
    expect($toasts)->toBeArray()
        ->and($toasts[0]['message'])->toBe('Ended — nothing further will be charged')
        ->and($toasts[0]['type'])->toBe('success');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return array_key_first($body) === 'ARBCancelSubscriptionRequest';
    });

    $subscription->refresh();
    $this->assertNotNull($subscription->cancellation_requested_at);

    $this->assertDatabaseHas('audit_log', [
        'business_id' => $business->id,
        'action' => 'billing.subscription_cancellation_requested',
        'actor' => 'user:'.$owner->getKey(),
    ]);
});

it('surfaces AlreadyEnded if already canceled at vendor', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $owner->id]);

    Subscription::factory()->create([
        'business_id' => $business->id,
        'gateway' => PaymentGateway::AuthorizeNet,
        'authorize_net_subscription_id' => 'sub_123',
        'stripe_customer_id' => null,
        'stripe_subscription_id' => null,
        'status' => SubscriptionStatus::Active,
        'term' => BillingTerm::Monthly,
    ]);

    Http::fake([
        '*request.api' => function ($request) {
            $body = json_decode($request->body(), true);
            $rootKey = array_key_first($body) ?? '';

            if ($rootKey === 'ARBGetSubscriptionStatusRequest') {
                return Http::response([
                    'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001']]],
                    'status' => 'canceled',
                ]);
            }

            return Http::response([], 404);
        },
    ]);

    $response = $this->actingAs($owner)
        ->withSession(['tenant_id' => $business->id])
        ->post(route('account.plan.cancel'), ['confirm' => 'on']);

    $response->assertRedirect();
    $toasts = session('toasts');
    expect($toasts)->toBeArray()
        ->and($toasts[0]['message'])->toBe('Your plan had already ended, so nothing more will be charged')
        ->and($toasts[0]['type'])->toBe('success');

    Http::assertNotSent(function ($request) {
        $body = json_decode($request->body(), true);

        return array_key_first($body) === 'ARBCancelSubscriptionRequest';
    });
});

it('surfaces NoFurtherCharge if expired at vendor', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $owner->id]);

    Subscription::factory()->create([
        'business_id' => $business->id,
        'gateway' => PaymentGateway::AuthorizeNet,
        'authorize_net_subscription_id' => 'sub_123',
        'stripe_customer_id' => null,
        'stripe_subscription_id' => null,
        'status' => SubscriptionStatus::Active,
        'term' => BillingTerm::Monthly,
    ]);

    Http::fake([
        '*request.api' => function ($request) {
            $body = json_decode($request->body(), true);
            $rootKey = array_key_first($body) ?? '';

            if ($rootKey === 'ARBGetSubscriptionStatusRequest') {
                return Http::response([
                    'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001']]],
                    'status' => 'expired',
                ]);
            }

            return Http::response([], 404);
        },
    ]);

    $response = $this->actingAs($owner)
        ->withSession(['tenant_id' => $business->id])
        ->post(route('account.plan.cancel'), ['confirm' => 'on']);

    $response->assertRedirect();
    $toasts = session('toasts');
    expect($toasts)->toBeArray()
        ->and($toasts[0]['message'])->toBe('Nothing further will be charged — every payment for your plan had already been taken')
        ->and($toasts[0]['type'])->toBe('success');

    Http::assertNotSent(function ($request) {
        $body = json_decode($request->body(), true);

        return array_key_first($body) === 'ARBCancelSubscriptionRequest';
    });
});

it('refuses staff cancellation', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $owner->id]);
    $staff = User::factory()->create(['role' => UserRole::Staff]);

    Subscription::factory()->create([
        'business_id' => $business->id,
        'gateway' => PaymentGateway::AuthorizeNet,
        'authorize_net_subscription_id' => 'sub_123',
        'stripe_customer_id' => null,
        'stripe_subscription_id' => null,
        'status' => SubscriptionStatus::Active,
        'term' => BillingTerm::Monthly,
    ]);

    Http::fake();

    $response = $this->actingAs($staff)
        ->withSession(['tenant_id' => $business->id])
        ->post(route('account.plan.cancel'), ['confirm' => 'on']);

    $response->assertForbidden();
});

it('refuses if in impersonation', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $owner->id]);

    Subscription::factory()->create([
        'business_id' => $business->id,
        'gateway' => PaymentGateway::AuthorizeNet,
        'authorize_net_subscription_id' => 'sub_123',
        'stripe_customer_id' => null,
        'stripe_subscription_id' => null,
        'status' => SubscriptionStatus::Active,
        'term' => BillingTerm::Monthly,
    ]);

    $session = ImpersonationSession::factory()->act()->create([
        'business_id' => $business->id,
    ]);

    Http::fake();

    $response = $this->actingAs($owner)
        ->withSession([
            'tenant_id' => $business->id,
            'impersonation.session_id' => $session->id,
            'impersonation.agent_id' => $session->agent_id,
        ])
        ->post(route('account.plan.cancel'), ['confirm' => 'on']);

    $response->assertForbidden();
});
