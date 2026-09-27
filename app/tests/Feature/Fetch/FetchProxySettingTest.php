<?php

declare(strict_types=1);

use App\Livewire\Admin\Credentials as CredentialsScreen;
use App\Models\User;
use App\Services\Fetch\DirectFetchGateway;
use App\Support\Admin\AdminAccess;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => true);
    $this->admin = User::factory()->create();
});

it('sends tenant-site fetches direct when no proxy credential is set', function () {
    expect(DirectFetchGateway::proxyOptionsFor('tenant_site'))->toBe([]);
});

it('routes only tenant-site fetches through the proxy once an operator sets one', function () {
    Livewire::actingAs($this->admin)
        ->test(CredentialsScreen::class)
        ->call('confirmRotate', 'fetch_proxy_url')
        ->set('draft', 'http://distinctive-4871:secret@proxy.example:8080')
        ->set('environment', 'live')
        ->call('save')
        ->assertHasNoErrors();

    expect(DirectFetchGateway::proxyOptionsFor('tenant_site'))->toBe(['proxy' => 'http://distinctive-4871:secret@proxy.example:8080'])
        ->and(DirectFetchGateway::proxyOptionsFor('google_places'))->toBe([]);
});

it('still fetches a tenant-site page with the proxy set', function () {
    Livewire::actingAs($this->admin)
        ->test(CredentialsScreen::class)
        ->call('confirmRotate', 'fetch_proxy_url')
        ->set('draft', 'http://distinctive-4871:secret@proxy.example:8080')
        ->set('environment', 'live')
        ->call('save');
    Http::fake(['*' => Http::response('<html><title>ok</title></html>', 200)]);

    $result = app(DirectFetchGateway::class)->fetch('tenant_site', 'https://distinctive-4872.example/');

    expect($result->successful())->toBeTrue();
});
