<?php

declare(strict_types=1);

use App\Exceptions\AuthorizeNetRequestFailed;
use App\Models\Business;
use App\Models\User;
use App\Services\Billing\AuthorizeNetGateway;
use App\Support\CardholderName;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

it('retries ARBCreateSubscriptionRequest exactly once if E00040 is returned', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $user->id]);

    Config::set('credentials.authorize_net_login_id', 'test_login');
    Config::set('credentials.authorize_net_transaction_key', 'test_key');
    Config::set('credentials.authorize_net_signature_key', 'test_sig');
    Config::set('credentials.authorize_net_public_client_key', 'test_pub');

    $attempts = 0;
    Http::fake([
        '*request.api' => function ($request) use (&$attempts) {
            $body = json_decode($request->body(), true);
            $rootKey = array_key_first($body);

            if ($rootKey === 'createCustomerProfileRequest') {
                return Http::response([
                    'messages' => [
                        'resultCode' => 'Ok',
                        'message' => [['code' => 'I00001', 'text' => 'Successful.']],
                    ],
                    'customerProfileId' => 'prof_1',
                    'customerPaymentProfileIdList' => ['pay_1'],
                ]);
            }
            if ($rootKey === 'getCustomerProfileRequest') {
                return Http::response([
                    'messages' => ['resultCode' => 'Error', 'message' => [['code' => 'E00040']]],
                ]);
            }
            if ($rootKey === 'ARBCreateSubscriptionRequest') {
                $attempts++;
                if ($attempts === 1) {
                    return Http::response(['messages' => ['resultCode' => 'Error', 'message' => [['code' => 'E00040']]]]);
                }

                return Http::response([
                    'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001']]],
                    'subscriptionId' => 'sub_123',
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
    expect($attempts)->toBe(2);
    expect($sub->authorize_net_subscription_id)->toBe('sub_123');
});

it('throws on other codes without retrying', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $user->id]);

    Config::set('credentials.authorize_net_login_id', 'test_login');
    Config::set('credentials.authorize_net_transaction_key', 'test_key');
    Config::set('credentials.authorize_net_signature_key', 'test_sig');
    Config::set('credentials.authorize_net_public_client_key', 'test_pub');

    $attempts = 0;
    Http::fake([
        '*request.api' => function ($request) use (&$attempts) {
            $body = json_decode($request->body(), true);
            $rootKey = array_key_first($body);

            if ($rootKey === 'createCustomerProfileRequest') {
                return Http::response([
                    'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001']]],
                    'customerProfileId' => 'prof_2',
                    'customerPaymentProfileIdList' => ['pay_2'],
                ]);
            }
            if ($rootKey === 'getCustomerProfileRequest') {
                return Http::response([
                    'messages' => ['resultCode' => 'Error', 'message' => [['code' => 'E00040']]],
                ]);
            }
            if ($rootKey === 'ARBCreateSubscriptionRequest') {
                $attempts++;

                return Http::response(['messages' => ['resultCode' => 'Error', 'message' => [['code' => 'E00027']]]]);
            }
            throw new RuntimeException('Unexpected request: '.$rootKey);
        },
    ]);

    $gateway = app(AuthorizeNetGateway::class);
    try {
        $gateway->subscribe(
            business: $business,
            email: 'test2@example.com',
            opaqueDataValue: 'foo_opaque_data2',
            cardholder: CardholderName::fromInput('Test2', 'User2')
        );
        $this->fail('Expected exception');
    } catch (AuthorizeNetRequestFailed $e) {
        expect($e->reason)->toBe('E00027');
    }
    // E00027 should only have 1 attempt because it shouldn't retry
    expect($attempts)->toBe(1);
});
