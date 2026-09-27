<?php

declare(strict_types=1);

use App\Exceptions\GbpRequestFailed;
use App\Models\Business;
use App\Models\GbpProfileBinding;
use App\Models\ZernioAccountBinding;
use App\Modules\CWhatsapp\Models\WhatsappConnection;
use App\Services\Config\DefaultsRegistry;
use App\Services\Gbp\GbpConnections;
use App\Services\Gbp\ZernioReconciliation;
use App\Services\Gbp\ZernioSpend;
use App\Services\Zernio\ZernioWhatsappClient;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    app(DefaultsRegistry::class)->set('whatsapp.zernio_enabled', true, 'test');
    config(['credentials.zernio_api_key' => 'test-key']);
});

it('fetches connect url', function () {
    Http::fake([
        'zernio.com/api/v1/connect/whatsapp*' => Http::response(['authUrl' => 'https://zernio.com/auth/wa-4960']),
    ]);

    $client = app(ZernioWhatsappClient::class);
    $url = $client->connectUrl('profile_4960', 'https://app.test/cb');

    expect($url)->toBe('https://zernio.com/auth/wa-4960');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'profileId=profile_4960')
            && str_contains($request->url(), 'redirect_url=https%3A%2F%2Fapp.test%2Fcb')
            && str_contains($request->url(), 'onboarding=api');
    });
});

it('throws when flag off', function () {
    app(DefaultsRegistry::class)->set('whatsapp.zernio_enabled', false, 'test');

    Http::fake();

    $client = app(ZernioWhatsappClient::class);

    expect(fn () => $client->connectUrl('profile_4960', 'https://app.test/cb'))
        ->toThrow(GbpRequestFailed::class);

    Http::assertNothingSent();
});

it('checks if profile owns account', function () {
    Http::fake([
        'zernio.com/api/v1/accounts*' => Http::response(['accounts' => [['_id' => 'acct_wa_4961', 'profileId' => 'profile_4960', 'platform' => 'whatsapp']]]),
    ]);

    $client = app(ZernioWhatsappClient::class);

    expect($client->profileOwnsAccount('profile_4960', 'acct_wa_4961'))->toBeTrue();
    expect($client->profileOwnsAccount('profile_4960', 'acct_other'))->toBeFalse();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'platform=whatsapp');
    });
});

it('throws when unreadable account data', function () {
    Http::fake([
        'zernio.com/api/v1/accounts*' => Http::response([]),
    ]);

    $client = app(ZernioWhatsappClient::class);

    expect(fn () => $client->profileOwnsAccount('profile_4960', 'acct_wa_4961'))
        ->toThrow(GbpRequestFailed::class);
});

it('suppresses 404 on disconnect', function () {
    Http::fake([
        'zernio.com/api/v1/accounts/acct_wa_4961' => Http::response([], 404),
    ]);

    $client = app(ZernioWhatsappClient::class);
    $client->disconnectAccount('acct_wa_4961');

    Http::assertSentCount(1);
});

it('isolates connections by tenant', function () {
    $bizA = Business::factory()->create();
    $bizB = Business::factory()->create();

    Tenancy::set((int) $bizA->id);
    WhatsappConnection::forceCreate(['business_id' => $bizA->id, 'account_ref' => 'ref_a', 'provider' => 'zernio']);

    Tenancy::set((int) $bizB->id);
    WhatsappConnection::forceCreate(['business_id' => $bizB->id, 'account_ref' => 'ref_b', 'provider' => 'zernio']);

    Tenancy::set((int) $bizA->id);
    expect(WhatsappConnection::count())->toBe(1)
        ->and(WhatsappConnection::first()->account_ref)->toBe('ref_a');

    Tenancy::set((int) $bizB->id);
    expect(WhatsappConnection::count())->toBe(1)
        ->and(WhatsappConnection::first()->account_ref)->toBe('ref_b');
});

it('adds to connectedAccounts spend count', function () {
    $spend = app(ZernioSpend::class);
    $before = $spend->connectedAccounts();

    ZernioAccountBinding::create(['account_ref' => 'new_wa', 'profile_ref' => 'prof', 'platform' => 'whatsapp']);

    expect($spend->connectedAccounts())->toBe($before + 1);
});

it('returns stored profile and makes no vendor call when flag is off', function () {
    app(DefaultsRegistry::class)->set('gbp.zernio_enabled', false, 'test');

    $business = Business::factory()->create();
    Tenancy::set((int) $business->id);

    GbpProfileBinding::forceCreate([
        'business_id' => $business->id,
        'profile_ref' => 'profile_5201',
    ]);

    Http::fake();

    $profile = app(GbpConnections::class)->zernioProfileForCurrentBusiness();

    expect($profile)->toBe('profile_5201');
    Http::assertNothingSent();
});

it('counts facebook platform in connectedAccounts and excludes from orphans', function () {
    app(DefaultsRegistry::class)->set('gbp.zernio_enabled', true, 'test');

    $business = Business::factory()->create();
    Tenancy::set((int) $business->id);

    $spend = app(ZernioSpend::class);
    $beforeCount = $spend->connectedAccounts();

    ZernioAccountBinding::create([
        'account_ref' => 'acct_fb_5202',
        'profile_ref' => 'profile_5201',
        'platform' => 'facebook',
    ]);

    expect($spend->connectedAccounts())->toBe($beforeCount + 1);

    Http::fake([
        'zernio.com/api/v1/accounts*' => Http::response(['accounts' => [
            [
                '_id' => 'acct_fb_5202',
                'profileId' => 'profile_5201',
                'platform' => 'facebook',
            ],
        ]]),
    ]);

    $recon = app(ZernioReconciliation::class)->run();

    expect($recon->orphans)->toBeEmpty();
});
