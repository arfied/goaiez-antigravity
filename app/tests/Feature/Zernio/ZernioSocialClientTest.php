<?php

declare(strict_types=1);

namespace Tests\Feature\Zernio;

use App\Exceptions\GbpRequestFailed;
use App\Services\Config\DefaultsRegistry;
use App\Services\Zernio\ZernioSocialClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

it('returns published outcome on 201', function () {
    app(DefaultsRegistry::class)->set('social.zernio_enabled', true, 'test');
    config(['credentials.zernio_api_key' => 'test-key']);

    Http::fake([
        'zernio.com/api/v1/posts' => Http::response([
            'post' => [
                '_id' => 'post_123',
                'status' => 'published',
                'platforms' => [
                    [
                        'platform' => 'facebook',
                        'status' => 'published',
                        'platformPostId' => 'fb_123',
                        'platformPostUrl' => 'https://fb.com/123',
                    ],
                    [
                        'platform' => 'instagram',
                        'status' => 'published',
                        'platformPostId' => 'ig_123',
                        'platformPostUrl' => 'https://ig.com/123',
                    ],
                ],
            ],
        ], 201),
    ]);

    $client = app(ZernioSocialClient::class);

    $receipt = $client->publish(
        [
            ['platform' => 'facebook', 'accountId' => 'a1'],
            ['platform' => 'instagram', 'accountId' => 'a2'],
        ],
        'Hello',
        [['type' => 'image', 'url' => 'https://img.com/1.jpg']],
        'social-publication-5301'
    );

    expect($receipt->outcome)->toBe('published')
        ->and($receipt->platforms)->toHaveCount(2)
        ->and($receipt->platforms['instagram']->platform)->toBe('instagram');

    Http::assertSent(function ($request) {
        return $request->hasHeader('Idempotency-Key', 'social-publication-5301')
            && $request['publishNow'] === true
            && $request['platforms'][1]['platform'] === 'instagram'
            && $request['mediaItems'][0]['url'] === 'https://img.com/1.jpg';
    });
});

it('returns partial outcome on 207 partial', function () {
    app(DefaultsRegistry::class)->set('social.zernio_enabled', true, 'test');
    config(['credentials.zernio_api_key' => 'test-key']);

    Http::fake([
        'zernio.com/api/v1/posts' => Http::response([
            'post' => [
                '_id' => 'post_123',
                'status' => 'partial',
                'platforms' => [
                    [
                        'platform' => 'facebook',
                        'status' => 'published',
                    ],
                    [
                        'platform' => 'instagram',
                        'status' => 'failed',
                        'errorMessage' => 'Media not supported',
                    ],
                ],
            ],
        ], 207),
    ]);

    $client = app(ZernioSocialClient::class);

    $receipt = $client->publish(
        [
            ['platform' => 'facebook', 'accountId' => 'a1'],
            ['platform' => 'instagram', 'accountId' => 'a2'],
        ],
        'Hello',
        [],
        'key'
    );

    expect($receipt->outcome)->toBe('partial')
        ->and($receipt->platforms['instagram']->errorMessage)->toBe('Media not supported');
});

it('returns publishing outcome on 207 scheduled', function () {
    app(DefaultsRegistry::class)->set('social.zernio_enabled', true, 'test');
    config(['credentials.zernio_api_key' => 'test-key']);

    Http::fake([
        'zernio.com/api/v1/posts' => Http::response([
            'post' => [
                '_id' => 'post_123',
                'status' => 'scheduled',
                'platforms' => [],
            ],
        ], 207),
    ]);

    $client = app(ZernioSocialClient::class);
    $receipt = $client->publish([['platform' => 'facebook', 'accountId' => 'a1']], 'Hello', [], 'key');

    expect($receipt->outcome)->toBe('publishing');
});

it('returns duplicate outcome on 409 existing post', function () {
    app(DefaultsRegistry::class)->set('social.zernio_enabled', true, 'test');
    config(['credentials.zernio_api_key' => 'test-key']);

    Http::fake([
        'zernio.com/api/v1/posts' => Http::response([
            'details' => ['existingPostId' => 'zp_5302'],
        ], 409),
    ]);

    $client = app(ZernioSocialClient::class);
    $receipt = $client->publish([['platform' => 'facebook', 'accountId' => 'a1']], 'Hello', [], 'key');

    expect($receipt->outcome)->toBe('duplicate')
        ->and($receipt->existingPostId)->toBe('zp_5302');
});

it('returns in_flight outcome on 409 idempotency conflict', function () {
    app(DefaultsRegistry::class)->set('social.zernio_enabled', true, 'test');
    config(['credentials.zernio_api_key' => 'test-key']);

    Http::fake([
        'zernio.com/api/v1/posts' => Http::response([
            'code' => 'idempotency_conflict',
        ], 409),
    ]);

    $client = app(ZernioSocialClient::class);
    $receipt = $client->publish([['platform' => 'facebook', 'accountId' => 'a1']], 'Hello', [], 'key');

    expect($receipt->outcome)->toBe('in_flight');
});

it('throws GbpRequestFailed on 403', function () {
    app(DefaultsRegistry::class)->set('social.zernio_enabled', true, 'test');
    config(['credentials.zernio_api_key' => 'test-key']);

    Http::fake([
        'zernio.com/api/v1/posts' => Http::response([
            'code' => 'ACCOUNT_DISCONNECTED',
        ], 403),
    ]);

    $client = app(ZernioSocialClient::class);
    $client->publish([['platform' => 'facebook', 'accountId' => 'a1']], 'Hello', [], 'key');
})->throws(GbpRequestFailed::class);

it('returns unconfirmed on 503', function () {
    app(DefaultsRegistry::class)->set('social.zernio_enabled', true, 'test');
    config(['credentials.zernio_api_key' => 'test-key']);

    Http::fake([
        'zernio.com/api/v1/posts' => Http::response([], 503),
    ]);

    $client = app(ZernioSocialClient::class);
    $receipt = $client->publish([['platform' => 'facebook', 'accountId' => 'a1']], 'Hello', [], 'key');

    expect($receipt->outcome)->toBe('unconfirmed');
    Http::assertSentCount(1);
});

it('returns unconfirmed on connection exception', function () {
    app(DefaultsRegistry::class)->set('social.zernio_enabled', true, 'test');
    config(['credentials.zernio_api_key' => 'test-key']);

    Http::fake(fn () => throw new ConnectionException('down'));

    $client = app(ZernioSocialClient::class);
    $receipt = $client->publish([['platform' => 'facebook', 'accountId' => 'a1']], 'Hello', [], 'key');

    expect($receipt->outcome)->toBe('unconfirmed');
});

it('throws GbpRequestFailed when flag is off', function () {
    app(DefaultsRegistry::class)->set('social.zernio_enabled', false, 'test');
    config(['credentials.zernio_api_key' => 'test-key']);

    Http::fake();

    $client = app(ZernioSocialClient::class);
    $client->publish([['platform' => 'facebook', 'accountId' => 'a1']], 'Hello', [], 'key');
})->throws(GbpRequestFailed::class);

it('asserts nothing sent when flag is off', function () {
    app(DefaultsRegistry::class)->set('social.zernio_enabled', false, 'test');
    config(['credentials.zernio_api_key' => 'test-key']);

    Http::fake();

    $client = app(ZernioSocialClient::class);
    try {
        $client->publish([['platform' => 'facebook', 'accountId' => 'a1']], 'Hello', [], 'key');
    } catch (\Throwable) {
    }

    Http::assertNothingSent();
});

it('throws InvalidArgumentException for twitter', function () {
    app(DefaultsRegistry::class)->set('social.zernio_enabled', true, 'test');
    config(['credentials.zernio_api_key' => 'test-key']);

    Http::fake();

    $client = app(ZernioSocialClient::class);
    $client->publish([['platform' => 'twitter', 'accountId' => 'a1']], 'Hello', [], 'key');
})->throws(InvalidArgumentException::class);

it('asserts nothing sent for twitter', function () {
    app(DefaultsRegistry::class)->set('social.zernio_enabled', true, 'test');
    config(['credentials.zernio_api_key' => 'test-key']);

    Http::fake();

    $client = app(ZernioSocialClient::class);
    try {
        $client->publish([['platform' => 'twitter', 'accountId' => 'a1']], 'Hello', [], 'key');
    } catch (\Throwable) {
    }

    Http::assertNothingSent();
});
