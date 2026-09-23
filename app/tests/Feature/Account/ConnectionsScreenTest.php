<?php

declare(strict_types=1);

use App\Contracts\SearchConsoleClient;
use App\Enums\GbpConnectionStatus;
use App\Enums\GbpProvider;
use App\Enums\UserRole;
use App\Livewire\Account\Connections;
use App\Models\GbpConnection;
use App\Models\Location;
use App\Models\User;
use App\Services\Config\DefaultsRegistry;
use App\Services\Gsc\SiteProperty;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

uses(RefreshesTenantDatabase::class);

beforeEach(function () {
    /** @var TestCase $this */
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->biz = TestCase::provisionTenant(['owner_user_id' => $this->owner->id]);
    $this->actingAs($this->owner);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);

    config(['credentials.zernio_api_key' => 'sk_'.str_repeat('a', 64)]);
    (new DefaultsRegistry)->set('gbp.zernio_enabled', true, 'test');

    Http::preventStrayRequests();

    Mail::fake();
    Notification::fake();
});

it('renders the screen with no connection', function () {
    $response = $this->get(route('account.connections'));
    $response->assertOk()
        ->assertSee('Your account', false)
        ->assertSee('Google reviews')
        ->assertSee('Not connected.');

    Mail::assertNothingSent();
});

it('refuses staff on GET and on mount', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::forget();

    $this->get(route('account.connections'))->assertForbidden();

    Livewire::test(Connections::class)->assertForbidden();

    Mail::assertNothingSent();
});

it('connects a location by delegating to Zernio', function () {
    $location = Location::factory()->forBusiness($this->biz->id)->create();

    Http::fake([
        'zernio.com/api/v1/profiles*' => Http::response(['profiles' => [['_id' => 'profile_123']]]),
        'zernio.com/api/v1/connect/googlebusiness*' => Http::response(['authUrl' => 'https://zernio.com/auth/123']),
    ]);

    Livewire::test(Connections::class)
        ->call('connect', $location->id)
        ->assertRedirect('https://zernio.com/auth/123');

    $this->assertDatabaseHas('gbp_connections', [
        'business_id' => $this->biz->id,
        'location_id' => $location->id,
        'status' => GbpConnectionStatus::Pending->value,
        'provider' => GbpProvider::Zernio->value,
        'account_ref' => null, // pending has no account_ref
        'provider_profile_ref' => 'profile_123',
    ]);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/connect/googlebusiness'));

    Mail::assertNothingSent();
});

it('refuses to connect when Zernio is disabled', function () {
    (new DefaultsRegistry)->set('gbp.zernio_enabled', false, 'test');

    $location = Location::factory()->forBusiness($this->biz->id)->create();

    Livewire::test(Connections::class)
        ->call('connect', $location->id)
        ->assertDispatched('toaster:received'); // refusal toast

    $this->assertDatabaseMissing('gbp_connections', [
        'location_id' => $location->id,
    ]);

    Http::assertNothingSent();

    Mail::assertNothingSent();
});

it('checks a connection when healthy', function () {
    $location = Location::factory()->forBusiness($this->biz->id)->create();
    $connection = GbpConnection::query()->forceCreate([
        'business_id' => $this->biz->id,
        'location_id' => $location->id,
        'status' => GbpConnectionStatus::Connected,
        'provider' => GbpProvider::Zernio,
        'account_ref' => 'acct_screen_0003',
    ]);

    Http::fake([
        'zernio.com/api/v1/accounts/acct_screen_0003/health*' => Http::response(['status' => 'healthy'], 200),
    ]);

    Livewire::test(Connections::class)
        ->call('check', $connection->id);

    $this->assertDatabaseHas('gbp_connections', [
        'id' => $connection->id,
        'status' => GbpConnectionStatus::Connected->value,
    ]);
    $this->assertNotNull($connection->refresh()->last_checked_at);

    Mail::assertNothingSent();
});

it('checks a connection when degraded', function () {
    $location = Location::factory()->forBusiness($this->biz->id)->create();
    $connection = GbpConnection::query()->forceCreate([
        'business_id' => $this->biz->id,
        'location_id' => $location->id,
        'status' => GbpConnectionStatus::Connected,
        'provider' => GbpProvider::Zernio,
        'account_ref' => 'acct_screen_0004',
    ]);

    Http::fake([
        'zernio.com/api/v1/accounts/acct_screen_0004/health*' => Http::response(['platform' => 'googlebusiness'], 403),
    ]);

    Livewire::test(Connections::class)
        ->call('check', $connection->id);

    $this->assertDatabaseHas('gbp_connections', [
        'id' => $connection->id,
        'status' => GbpConnectionStatus::Disconnected->value,
    ]);
    $this->assertNotNull($connection->refresh()->last_checked_at);

    Mail::assertNothingSent();
});

it('disconnects a connection', function () {
    $location = Location::factory()->forBusiness($this->biz->id)->create();
    $connection = GbpConnection::query()->forceCreate([
        'business_id' => $this->biz->id,
        'location_id' => $location->id,
        'status' => GbpConnectionStatus::Connected,
        'provider' => GbpProvider::Zernio,
        'account_ref' => 'acct_screen_0005',
    ]);

    Http::fake([
        'zernio.com/api/v1/accounts/acct_screen_0005*' => Http::response([], 200),
    ]);

    Livewire::test(Connections::class)
        ->call('disconnect', $connection->id);

    $this->assertDatabaseHas('gbp_connections', [
        'id' => $connection->id,
        'status' => GbpConnectionStatus::Disconnected->value,
    ]);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/accounts/acct_screen_0005'));

    Mail::assertNothingSent();
});

it('manages search console property', function () {
    $location = Location::factory()->forBusiness($this->biz->id)->create();

    $client = Mockery::mock(SearchConsoleClient::class);
    $client->shouldReceive('properties')->andReturn([
        SiteProperty::fromApi(['siteUrl' => 'https://example.com/', 'permissionLevel' => 'siteOwner']),
    ]);
    app()->instance(SearchConsoleClient::class, $client);

    $component = Livewire::test(Connections::class)
        ->call('openSearchConsolePicker', $location->id);

    $component->assertSet('gscPropertyPickerLocationId', $location->id);
    $properties = $component->get('gscAvailableProperties');
    $this->assertCount(1, $properties);
    $this->assertSame('https://example.com/', $properties[0]['site_url']);

    $component->set('gscSelectedSiteUrl.'.$location->id, 'https://example.com/')
        ->call('chooseSearchConsoleProperty', $location->id);

    $this->assertDatabaseHas('gsc_site_properties', [
        'business_id' => $this->biz->id,
        'location_id' => $location->id,
        'site_url' => 'https://example.com/',
    ]);

    $component->call('clearSearchConsoleProperty', $location->id);

    $this->assertDatabaseMissing('gsc_site_properties', [
        'location_id' => $location->id,
    ]);

    Mail::assertNothingSent();
});

it('refuses to interact with other tenants connection', function () {
    $otherBiz = TestCase::provisionTenant(['owner_user_id' => User::factory()->create()->id]);
    $otherLocation = Location::factory()->forBusiness($otherBiz->id)->create();
    $otherConnection = GbpConnection::query()->forceCreate([
        'business_id' => $otherBiz->id,
        'location_id' => $otherLocation->id,
        'status' => GbpConnectionStatus::Connected,
        'provider' => GbpProvider::Zernio,
        'account_ref' => 'acct_screen_other',
    ]);
    Tenancy::set((int) $this->biz->id); // Restore our own tenancy context for the test assertions!

    // In Livewire 3 testing, calling a method that throws ModelNotFoundException
    // natively throws it up. To test the 404 behavior, we can catch it or use expectException.
    expect(fn () => Livewire::test(Connections::class)->call('check', $otherConnection->id))
        ->toThrow(ModelNotFoundException::class);

    expect(fn () => Livewire::test(Connections::class)->call('disconnect', $otherConnection->id))
        ->toThrow(ModelNotFoundException::class);

    Tenancy::set((int) $otherBiz->id);
    $this->assertDatabaseHas('gbp_connections', [
        'id' => $otherConnection->id,
        'status' => GbpConnectionStatus::Connected->value,
    ]);

    Mail::assertNothingSent();
});

afterEach(function () {
    Tenancy::forget();
});
