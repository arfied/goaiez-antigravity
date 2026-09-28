<?php

declare(strict_types=1);

use App\Exceptions\GbpRequestFailed;
use App\Services\Config\DefaultsRegistry;
use App\Services\Zernio\ZernioAccounts;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    app(DefaultsRegistry::class)->set('social.zernio_enabled', true, 'test');
    Config::set('credentials.zernio_api_key', 'test-key');
});

test('a connectUrl sends profileId and redirect_url, throws for twitter', function () {
    Http::fake([
        'zernio.com/api/v1/connect/facebook*' => Http::response(['authUrl' => 'https://zernio.com/auth/fb-6002']),
    ]);

    $accounts = app(ZernioAccounts::class);
    $url = $accounts->connectUrl('facebook', 'profile_6001', 'https://app.test/cb');

    expect($url)->toBe('https://zernio.com/auth/fb-6002');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://zernio.com/api/v1/connect/facebook?profileId=profile_6001&redirect_url=https%3A%2F%2Fapp.test%2Fcb';
    });

    $thrown = false;
    try {
        $accounts->connectUrl('twitter', 'profile_6001', 'https://app.test/cb');
    } catch (InvalidArgumentException $e) {
        $thrown = true;
    }
    expect($thrown)->toBeTrue();
});

test('a connectUrl throws when flag is off', function () {
    app(DefaultsRegistry::class)->set('social.zernio_enabled', false, 'test');

    $accounts = app(ZernioAccounts::class);

    $thrown = false;
    try {
        $accounts->connectUrl('facebook', 'profile_6001', 'https://app.test/cb');
    } catch (GbpRequestFailed $e) {
        $thrown = true;
    }
    expect($thrown)->toBeTrue();
    Http::assertNothingSent();
});

test('b accountsOnProfile sends platform and includeOverLimit', function () {
    Http::fake([
        'zernio.com/api/v1/accounts*' => Http::response(['accounts' => [['_id' => 'acct_fb_6003']]]),
    ]);

    $accounts = app(ZernioAccounts::class);
    $ids = $accounts->accountsOnProfile('facebook', 'profile_6001');

    expect($ids)->toBe(['acct_fb_6003']);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'profileId=profile_6001') &&
               str_contains($request->url(), 'platform=facebook') &&
               str_contains($request->url(), 'includeOverLimit=true');
    });
});

test('b accountsOnProfile throws on non-array', function () {
    Http::fake([
        'zernio.com/api/v1/accounts*' => Http::response(['accounts' => 'not an array']),
    ]);

    $accounts = app(ZernioAccounts::class);

    $thrown = false;
    try {
        $accounts->accountsOnProfile('facebook', 'profile_6001');
    } catch (GbpRequestFailed $e) {
        $thrown = true;
    }
    expect($thrown)->toBeTrue();
});

test('c disconnectAccount no throw on 404', function () {
    Http::fake([
        'zernio.com/api/v1/accounts/acct_fb_6003' => Http::response([], 404),
    ]);

    $accounts = app(ZernioAccounts::class);
    $accounts->disconnectAccount('facebook', 'acct_fb_6003');

    Http::assertSent(function ($request) {
        return $request->method() === 'DELETE' &&
               str_contains($request->url(), '/accounts/acct_fb_6003');
    });

    // No exception should be thrown
    expect(true)->toBeTrue();
});
