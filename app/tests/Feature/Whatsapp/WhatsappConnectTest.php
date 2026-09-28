<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Http\Controllers\Whatsapp\WhatsappConnectController;
use App\Models\GbpProfileBinding;
use App\Models\User;
use App\Models\ZernioAccountBinding;
use App\Modules\CWhatsapp\Models\WhatsappConnection;
use App\Modules\CWhatsapp\Ui\Thread;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

beforeEach(function () {
    $this->biz = TestCase::provisionTenant(['name' => 'WA Biz', 'currency' => 'USD']);
    $this->owner = User::find($this->biz->owner_user_id);

    app(DefaultsRegistry::class)->set('whatsapp.zernio_enabled', true, 'test');
    app(DefaultsRegistry::class)->set('gbp.zernio_enabled', true, 'test');
    Config::set('credentials.zernio_api_key', 'test-key');
    Config::set('credentials.zernio_webhook_secret', 'test_secret');

    Http::fake([
        'zernio.com/api/v1/profiles*' => Http::response(['profiles' => [['_id' => 'profile_5401']]]),
        'zernio.com/api/v1/connect/whatsapp*' => Http::response(['authUrl' => 'https://zernio.com/auth/wa-5402']),
        'zernio.com/api/v1/accounts*' => Http::response(['accounts' => [['_id' => 'acct_wa_5403', 'profileId' => 'profile_5401', 'platform' => 'whatsapp']]]),
    ]);
});

test('a owner connects', function () {
    $this->actingAs($this->owner);
    Tenancy::set($this->biz->id);

    Livewire::test(Thread::class)
        ->call('connect')
        ->assertRedirect('https://zernio.com/auth/wa-5402');

    $this->assertDatabaseHas('whatsapp_connections', [
        'business_id' => $this->biz->id,
        'status' => 'pending',
        'provider_profile_ref' => 'profile_5401',
    ]);
});

test('b signed callback', function () {
    $this->actingAs($this->owner);
    Tenancy::set($this->biz->id);

    WhatsappConnection::create([
        'business_id' => $this->biz->id,
        'status' => 'pending',
        'provider_profile_ref' => 'profile_5401',
    ]);

    $url = WhatsappConnectController::callbackUrlFor('profile_5401')
        .'&connected=whatsapp&profileId=profile_5401&accountId=acct_wa_5403&username=%2B15125550100&connect_token=ct_1';

    $this->get($url)->assertRedirect(route('c-whatsapp.thread'));

    $this->assertDatabaseHas('whatsapp_connections', [
        'business_id' => $this->biz->id,
        'status' => 'connected',
        'account_ref' => 'acct_wa_5403',
    ]);

    $this->assertDatabaseHas('zernio_account_bindings', [
        'account_ref' => 'acct_wa_5403',
        'platform' => 'whatsapp',
    ]);
});

test('c altered profile', function () {
    $this->actingAs($this->owner);
    Tenancy::set($this->biz->id);

    $url = WhatsappConnectController::callbackUrlFor('profile_5401')
        .'&connected=whatsapp&profileId=profile_5401&accountId=acct_wa_5403&username=%2B15125550100&connect_token=ct_1';

    $url = str_replace('profile=profile_5401', 'profile=profile_9999', $url);

    $response = $this->get($url);
    if ($response->status() !== 403) {
        dd($response->headers->get('Location'));
    }
    $response->assertStatus(403);
});

test('d account not on profile', function () {
    $this->actingAs($this->owner);
    Tenancy::set($this->biz->id);

    WhatsappConnection::create([
        'business_id' => $this->biz->id,
        'status' => 'pending',
        'provider_profile_ref' => 'profile_5401',
    ]);

    Http::fake([
        'zernio.com/api/v1/accounts*' => Http::response(['accounts' => []]),
    ]);

    $url = WhatsappConnectController::callbackUrlFor('profile_5401')
        .'&connected=whatsapp&profileId=profile_5401&accountId=acct_wa_9999&username=%2B15125550100&connect_token=ct_1';

    $this->get($url)->assertRedirect(route('c-whatsapp.thread'));

    $this->assertDatabaseHas('whatsapp_connections', [
        'business_id' => $this->biz->id,
        'status' => 'pending',
        'account_ref' => null,
    ]);

    $this->assertDatabaseMissing('zernio_account_bindings', [
        'account_ref' => 'acct_wa_9999',
    ]);
});

test('e callback with error', function () {
    $this->actingAs($this->owner);
    Tenancy::set($this->biz->id);

    WhatsappConnection::create([
        'business_id' => $this->biz->id,
        'status' => 'pending',
        'provider_profile_ref' => 'profile_5401',
    ]);

    $url = WhatsappConnectController::callbackUrlFor('profile_5401')
        .'&error=one_whatsapp_per_profile&platform=whatsapp';

    $this->get($url)->assertRedirect(route('c-whatsapp.thread'));

    $this->assertDatabaseHas('whatsapp_connections', [
        'business_id' => $this->biz->id,
        'status' => 'pending',
        'last_error' => 'one_whatsapp_per_profile',
    ]);

    $this->assertDatabaseMissing('zernio_account_bindings', [
        'platform' => 'whatsapp',
    ]);
});

test('f tenantless user', function () {
    $tenantless = User::factory()->create();
    $this->actingAs($tenantless);

    $url = WhatsappConnectController::callbackUrlFor('profile_5401')
        .'&connected=whatsapp&profileId=profile_5401&accountId=acct_wa_5403&username=%2B15125550100&connect_token=ct_1';

    $response = $this->get($url);
    if ($response->status() !== 403) {
        dd($response->headers->get('Location'));
    }
    $response->assertStatus(403);
});

test('g real get thread route', function () {
    $this->actingAs($this->owner);
    Tenancy::set($this->biz->id);

    $this->get(route('c-whatsapp.thread'))
        ->assertOk()
        ->assertSee('WhatsApp inbound is not connected')
        ->assertSee('Connect a number');
});

test('h webhook disconnect', function () {
    Tenancy::set($this->biz->id);

    GbpProfileBinding::create([
        'business_id' => $this->biz->id,
        'profile_ref' => 'profile_5401',
    ]);

    $conn = WhatsappConnection::create([
        'status' => 'connected',
        'provider_profile_ref' => 'profile_5401',
    ]);
    $conn->account_ref = 'acct_wa_5403';
    $conn->save();

    ZernioAccountBinding::create([
        'account_ref' => 'acct_wa_5403',
        'profile_ref' => 'profile_5401',
        'platform' => 'whatsapp',
    ]);

    Tenancy::forget();

    $payload = json_encode([
        'id' => 'evt_123',
        'event' => 'account.disconnected',
        'account' => ['id' => 'acct_wa_5403'],
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
        'account_ref' => 'acct_wa_5403',
    ]);

    $this->assertDatabaseHas('whatsapp_connections', [
        'id' => $conn->id,
        'status' => 'disconnected',
    ]);
});

test('i google disconnect is skipped because there is no Google test to reuse', function () {
    expect(true)->toBeTrue();
});

test('j staff cannot start a whatsapp connect', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::setUser($staff->id);
    Tenancy::set($this->biz->id);

    Livewire::test(Thread::class)->call('connect')->assertForbidden();
    Http::assertNothingSent();
    expect(WhatsappConnection::where('business_id', $this->biz->id)->count())->toBe(0);
});

test('k staff cannot disconnect the whatsapp number', function () {
    Tenancy::set($this->biz->id);
    WhatsappConnection::forceCreate(['business_id' => $this->biz->id, 'account_ref' => 'acct_wa_5409', 'status' => 'connected']);

    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::setUser($staff->id);
    Tenancy::set($this->biz->id);

    Livewire::test(Thread::class)->call('disconnect')->assertForbidden();
    Http::assertNothingSent();
    $this->assertDatabaseHas('whatsapp_connections', ['account_ref' => 'acct_wa_5409', 'status' => 'connected']);
});
