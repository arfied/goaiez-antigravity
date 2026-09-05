<?php

declare(strict_types=1);

use App\Exceptions\AuthorizeNetRequestFailed;
use App\Models\Business;
use App\Models\User;
use App\Services\Billing\AuthorizeNetGateway;
use App\Support\CardholderName;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

it('reuses the existing payment profile ID if E00039 is returned during createPaymentProfile', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $user->id]);

    Config::set('credentials.authorize_net_login_id', 'test_login');
    Config::set('credentials.authorize_net_transaction_key', 'test_key');
    Config::set('credentials.authorize_net_signature_key', 'test_sig');
    Config::set('credentials.authorize_net_public_client_key', 'test_pub');

    $subscriptionCreated = false;

    Http::fake([
        '*request.api' => function ($request) use (&$subscriptionCreated) {
            $body = json_decode($request->body(), true);
            $rootKey = array_key_first($body);

            if ($rootKey === 'getCustomerProfileRequest') {
                return Http::response([
                    'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001']]],
                    'profile' => [
                        'customerProfileId' => 'prof_existing',
                    ],
                ]);
            }
            if ($rootKey === 'createCustomerPaymentProfileRequest') {
                return Http::response([
                    'messages' => [
                        'resultCode' => 'Error',
                        'message' => [['code' => 'E00039', 'text' => 'A duplicate customer payment profile already exists.']],
                    ],
                    'customerPaymentProfileId' => 'pay_dup',
                ]);
            }
            if ($rootKey === 'ARBCreateSubscriptionRequest') {
                $subscriptionCreated = true;

                // Assert it used the right profile and payment profile IDs
                $sub = $body[$rootKey]['subscription'] ?? [];
                if (($sub['profile']['customerProfileId'] ?? '') !== 'prof_existing' ||
                    ($sub['profile']['customerPaymentProfileId'] ?? '') !== 'pay_dup') {
                    throw new RuntimeException('Wrong profile IDs used in subscription create.');
                }

                return Http::response([
                    'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001']]],
                    'subscriptionId' => 'sub_456',
                ]);
            }
            throw new RuntimeException('Unexpected request: '.$rootKey);
        },
    ]);

    $gateway = app(AuthorizeNetGateway::class);
    $sub = $gateway->subscribe(
        business: $business,
        email: 'test@example.com',
        opaqueDataValue: 'foo_opaque_data',
        cardholder: CardholderName::fromInput('Test', 'User')
    );

    expect($subscriptionCreated)->toBeTrue();
    expect($sub->authorize_net_subscription_id)->toBe('sub_456');
});

it('throws immediately without returning ID for E00027 during createPaymentProfile', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $user->id]);

    Config::set('credentials.authorize_net_login_id', 'test_login');
    Config::set('credentials.authorize_net_transaction_key', 'test_key');
    Config::set('credentials.authorize_net_signature_key', 'test_sig');
    Config::set('credentials.authorize_net_public_client_key', 'test_pub');

    Http::fake([
        '*request.api' => function ($request) {
            $body = json_decode($request->body(), true);
            $rootKey = array_key_first($body);

            if ($rootKey === 'getCustomerProfileRequest') {
                return Http::response([
                    'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001']]],
                    'profile' => [
                        'customerProfileId' => 'prof_existing',
                    ],
                ]);
            }
            if ($rootKey === 'createCustomerPaymentProfileRequest') {
                return Http::response([
                    'messages' => [
                        'resultCode' => 'Error',
                        'message' => [['code' => 'E00027', 'text' => 'Test error']],
                    ],
                ]);
            }
            throw new RuntimeException('Unexpected request: '.$rootKey);
        },
    ]);

    $gateway = app(AuthorizeNetGateway::class);
    try {
        $gateway->subscribe(
            business: $business,
            email: 'test@example.com',
            opaqueDataValue: 'foo_opaque_data',
            cardholder: CardholderName::fromInput('Test', 'User')
        );
        $this->fail('Expected exception');
    } catch (AuthorizeNetRequestFailed $e) {
        expect($e->reason)->toBe('E00027');
    }
});

it('reuses the existing subscription ID if E00012 is returned during ARBCreateSubscriptionRequest and ID is in response', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $user->id]);

    Config::set('credentials.authorize_net_login_id', 'test_login');
    Config::set('credentials.authorize_net_transaction_key', 'test_key');
    Config::set('credentials.authorize_net_signature_key', 'test_sig');
    Config::set('credentials.authorize_net_public_client_key', 'test_pub');

    $apiCalls = 0;

    Http::fake([
        '*request.api' => function ($request) use (&$apiCalls) {
            $apiCalls++;
            $body = json_decode($request->body(), true);
            $rootKey = array_key_first($body);

            if ($rootKey === 'getCustomerProfileRequest') {
                return Http::response([
                    'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001']]],
                    'profile' => [
                        'customerProfileId' => 'prof_existing',
                    ],
                ]);
            }
            if ($rootKey === 'createCustomerPaymentProfileRequest') {
                return Http::response([
                    'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001']]],
                    'customerPaymentProfileId' => 'pay_existing',
                ]);
            }
            if ($rootKey === 'ARBCreateSubscriptionRequest') {
                return Http::response([
                    'messages' => [
                        'resultCode' => 'Error',
                        'message' => [['code' => 'E00012', 'text' => 'A duplicate subscription already exists.']],
                    ],
                    'subscriptionId' => 'sub_duplicate_id_from_response',
                ]);
            }
            throw new RuntimeException('Unexpected request: '.$rootKey);
        },
    ]);

    $gateway = app(AuthorizeNetGateway::class);
    $sub = $gateway->subscribe(
        business: $business,
        email: 'test@example.com',
        opaqueDataValue: 'foo_opaque_data',
        cardholder: CardholderName::fromInput('Test', 'User')
    );

    expect($sub->authorize_net_subscription_id)->toBe('sub_duplicate_id_from_response');
    expect($apiCalls)->toBe(3); // profile, payment profile, subscription
});

it('fetches list to reuse the existing subscription ID if E00012 is returned without ID', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $user->id]);

    Config::set('credentials.authorize_net_login_id', 'test_login');
    Config::set('credentials.authorize_net_transaction_key', 'test_key');
    Config::set('credentials.authorize_net_signature_key', 'test_sig');
    Config::set('credentials.authorize_net_public_client_key', 'test_pub');

    $apiCalls = 0;

    Http::fake([
        '*request.api' => function ($request) use (&$apiCalls) {
            $apiCalls++;
            $body = json_decode($request->body(), true);
            $rootKey = array_key_first($body);

            if ($rootKey === 'getCustomerProfileRequest') {
                return Http::response([
                    'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001']]],
                    'profile' => [
                        'customerProfileId' => 'prof_existing',
                    ],
                ]);
            }
            if ($rootKey === 'createCustomerPaymentProfileRequest') {
                return Http::response([
                    'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001']]],
                    'customerPaymentProfileId' => 'pay_existing',
                ]);
            }
            if ($rootKey === 'ARBCreateSubscriptionRequest') {
                return Http::response([
                    'messages' => [
                        'resultCode' => 'Error',
                        'message' => [['code' => 'E00012', 'text' => 'A duplicate subscription already exists.']],
                    ],
                ]);
            }
            if ($rootKey === 'ARBGetSubscriptionListRequest') {
                return Http::response([
                    'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001']]],
                    'subscriptionDetails' => [
                        [
                            'id' => 'sub_found_in_list',
                            'name' => 'GO AI EZ — Base, monthly', // Must match the plan name in subscribe!
                            'status' => 'active',
                            'customerProfileId' => 'prof_existing',
                            'customerPaymentProfileId' => 'pay_existing',
                        ],
                    ],
                ]);
            }
            throw new RuntimeException('Unexpected request: '.$rootKey);
        },
    ]);

    $gateway = app(AuthorizeNetGateway::class);
    $sub = $gateway->subscribe(
        business: $business,
        email: 'test@example.com',
        opaqueDataValue: 'foo_opaque_data',
        cardholder: CardholderName::fromInput('Test', 'User')
    );

    expect($sub->authorize_net_subscription_id)->toBe('sub_found_in_list');
    expect($apiCalls)->toBe(4); // profile, payment profile, create sub, list sub
});

it('throws immediately without returning ID for E00027 during ARBCreateSubscriptionRequest', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $user->id]);

    Config::set('credentials.authorize_net_login_id', 'test_login');
    Config::set('credentials.authorize_net_transaction_key', 'test_key');
    Config::set('credentials.authorize_net_signature_key', 'test_sig');
    Config::set('credentials.authorize_net_public_client_key', 'test_pub');

    $apiCalls = 0;

    Http::fake([
        '*request.api' => function ($request) use (&$apiCalls) {
            $apiCalls++;
            $body = json_decode($request->body(), true);
            $rootKey = array_key_first($body);

            if ($rootKey === 'getCustomerProfileRequest') {
                return Http::response([
                    'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001']]],
                    'profile' => [
                        'customerProfileId' => 'prof_existing',
                    ],
                ]);
            }
            if ($rootKey === 'createCustomerPaymentProfileRequest') {
                return Http::response([
                    'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001']]],
                    'customerPaymentProfileId' => 'pay_existing',
                ]);
            }
            if ($rootKey === 'ARBCreateSubscriptionRequest') {
                return Http::response([
                    'messages' => [
                        'resultCode' => 'Error',
                        'message' => [['code' => 'E00027', 'text' => 'Test error']],
                    ],
                ]);
            }
            throw new RuntimeException('Unexpected request: '.$rootKey);
        },
    ]);

    $gateway = app(AuthorizeNetGateway::class);
    try {
        $gateway->subscribe(
            business: $business,
            email: 'test@example.com',
            opaqueDataValue: 'foo_opaque_data',
            cardholder: CardholderName::fromInput('Test', 'User')
        );
        $this->fail('Expected exception');
    } catch (AuthorizeNetRequestFailed $e) {
        expect($e->reason)->toBe('E00027');
    }

    expect($apiCalls)->toBe(3); // profile, payment profile, create sub
});
