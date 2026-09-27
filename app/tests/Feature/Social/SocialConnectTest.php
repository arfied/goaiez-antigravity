<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Http\Controllers\Social\SocialConnectController;
use App\Models\GbpProfileBinding;
use App\Models\Location;
use App\Models\User;
use App\Models\ZernioAccountBinding;
use App\Modules\X182\Models\SocialAccount;
use App\Modules\X182\Ui\ConnectedAccounts;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

beforeEach(function () {
    $this->biz = TestCase::provisionTenant(['name' => 'Social Biz', 'currency' => 'USD']);
    $this->owner = User::find($this->biz->owner_user_id);

    app(DefaultsRegistry::class)->set('whatsapp.zernio_enabled', true, 'test');
    app(DefaultsRegistry::class)->set('gbp.zernio_enabled', true, 'test');
    app(DefaultsRegistry::class)->set('social.zernio_enabled', true, 'test');
    Config::set('credentials.zernio_api_key', 'test-key');
    Config::set('credentials.zernio_webhook_secret', 'test_secret');

    Http::fake([
        'zernio.com/api/v1/profiles*' => Http::response(['profiles' => [['_id' => 'profile_6001']]]),
        'zernio.com/api/v1/connect/facebook*' => Http::response(['authUrl' => 'https://zernio.com/auth/fb-6002']),
        'zernio.com/api/v1/connect/instagram*' => Http::response(['authUrl' => 'https://zernio.com/auth/ig-6002']),
        'zernio.com/api/v1/accounts*instagram*' => Http::response(['accounts' => [['_id' => 'acct_ig_6004', 'profileId' => 'profile_6001', 'platform' => 'instagram']]]),
        'zernio.com/api/v1/accounts*' => Http::response(['accounts' => [['_id' => 'acct_fb_6003', 'profileId' => 'profile_6001', 'platform' => 'facebook']]]),
    ]);
});

test('a owner connects', function () {
    $this->actingAs($this->owner);
    Tenancy::set($this->biz->id);

    $locCount = Location::where('business_id', $this->biz->id)->count();
    if ($locCount === 0) {
        Location::create(['business_id' => $this->biz->id, 'name' => 'Loc 1']);
    } elseif ($locCount > 1) {
        Location::where('business_id', $this->biz->id)->skip(1)->take($locCount - 1)->delete();
    }

    Livewire::test(ConnectedAccounts::class)
        ->call('connect', 'facebook')
        ->assertRedirect('https://zernio.com/auth/fb-6002');
});

test('b signed callback', function () {
    $this->actingAs($this->owner);
    Tenancy::set($this->biz->id);

    SocialAccount::create([
        'business_id' => $this->biz->id,
        'platform' => 'facebook',
        'status' => 'pending',
        'provider_profile_ref' => 'profile_6001',
    ]);

    $url = SocialConnectController::callbackUrlFor('facebook', 'profile_6001')
        .'&connected=true&profileId=profile_6001&accountId=acct_fb_6003&username=joesdiner';

    $this->get($url)->assertRedirect(route('x-182.connected-accounts'));

    $this->assertDatabaseHas('social_accounts', [
        'business_id' => $this->biz->id,
        'platform' => 'facebook',
        'status' => 'connected',
        'account_ref' => 'acct_fb_6003',
    ]);
});

test('c instagram callback', function () {
    $this->actingAs($this->owner);
    Tenancy::set($this->biz->id);

    SocialAccount::create([
        'business_id' => $this->biz->id,
        'platform' => 'instagram',
        'status' => 'pending',
        'provider_profile_ref' => 'profile_6001',
    ]);

    $url = SocialConnectController::callbackUrlFor('instagram', 'profile_6001')
        .'&code=abc&state=xyz';

    $this->get($url)->assertRedirect(route('x-182.connected-accounts'));

    $this->assertDatabaseHas('social_accounts', [
        'business_id' => $this->biz->id,
        'platform' => 'instagram',
        'status' => 'connected',
        'account_ref' => 'acct_ig_6004',
    ]);
});

test('d altered profile', function () {
    $this->actingAs($this->owner);
    Tenancy::set($this->biz->id);

    $url = SocialConnectController::callbackUrlFor('facebook', 'profile_6001')
        .'&connected=true&profileId=profile_6001&accountId=acct_fb_6003&username=joesdiner';

    $url = str_replace('profile=profile_6001', 'profile=profile_9999', $url);

    $this->get($url)->assertStatus(403);
});

test('e error callback', function () {
    $this->actingAs($this->owner);
    Tenancy::set($this->biz->id);

    SocialAccount::create([
        'business_id' => $this->biz->id,
        'platform' => 'facebook',
        'status' => 'pending',
        'provider_profile_ref' => 'profile_6001',
    ]);

    $url = SocialConnectController::callbackUrlFor('facebook', 'profile_6001')
        .'&connected=false&error=access_denied';

    $this->get($url)->assertRedirect(route('x-182.connected-accounts'));

    $this->assertDatabaseHas('social_accounts', [
        'business_id' => $this->biz->id,
        'platform' => 'facebook',
        'status' => 'pending',
        'last_error' => 'access_denied',
    ]);
});

test('f unknown platform', function () {
    $this->actingAs($this->owner);
    Tenancy::set($this->biz->id);

    $url = URL::temporarySignedRoute(
        'social.connect.callback',
        now()->addMinutes(30),
        ['platform' => 'twitter', 'profile' => 'profile_6001']
    );

    $this->get($url)->assertStatus(404);
});

test('g real get route', function () {
    $this->actingAs($this->owner);
    Tenancy::set($this->biz->id);

    $this->get(route('x-182.connected-accounts'))
        ->assertOk()
        ->assertSee('Connect a Facebook Page');
});

test('h webhook disconnect', function () {
    Tenancy::set($this->biz->id);

    GbpProfileBinding::create([
        'business_id' => $this->biz->id,
        'profile_ref' => 'profile_6001',
    ]);

    $conn = SocialAccount::create([
        'business_id' => $this->biz->id,
        'platform' => 'facebook',
        'status' => 'connected',
        'is_connected' => true,
        'provider_profile_ref' => 'profile_6001',
    ]);
    $conn->account_ref = 'acct_fb_6003';
    $conn->save();

    ZernioAccountBinding::create([
        'account_ref' => 'acct_fb_6003',
        'profile_ref' => 'profile_6001',
        'platform' => 'facebook',
    ]);

    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_123',
        'event' => 'account.disconnected',
        'account' => ['id' => 'acct_fb_6003'],
    ], JSON_THROW_ON_ERROR);

    $sig = hash_hmac('sha256', $payload, 'test_secret');

    $response = $this->call(
        'POST',
        '/webhooks/zernio',
        [], [], [],
        ['HTTP_X_ZERNIO_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'],
        $payload,
    );

    $response->assertStatus(200);
    expect($response->json('ok'))->toBeTrue();
    expect($response->json('outcome'))->toBe('handled');

    Tenancy::set($this->biz->id);

    $this->assertDatabaseMissing('zernio_account_bindings', [
        'account_ref' => 'acct_fb_6003',
    ]);

    $this->assertDatabaseHas('social_accounts', [
        'id' => $conn->id,
        'status' => 'disconnected',
        'is_connected' => false,
    ]);
});

test('i staff cannot start a connect', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::setUser($staff->id);
    Tenancy::set($this->biz->id);

    Livewire::test(ConnectedAccounts::class)->call('connect', 'facebook')->assertForbidden();
    Http::assertNothingSent();
    expect(SocialAccount::where('business_id', $this->biz->id)->count())->toBe(0);
});

test('j staff cannot disconnect a connected page', function () {
    $conn = SocialAccount::create([
        'business_id' => $this->biz->id,
        'platform' => 'facebook',
        'status' => 'connected',
        'is_connected' => true,
        'provider_profile_ref' => 'profile_6001',
    ]);
    $conn->account_ref = 'acct_fb_6003';
    $conn->save();

    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::setUser($staff->id);
    Tenancy::set($this->biz->id);

    Livewire::test(ConnectedAccounts::class)->call('disconnect', $conn->id)->assertForbidden();
    Http::assertNothingSent();
    $this->assertDatabaseHas('social_accounts', ['id' => $conn->id, 'status' => 'connected', 'is_connected' => true]);
});
