<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Livewire\Admin\TenantLocations;
use App\Models\Business;
use App\Models\Location;
use App\Models\User;
use App\Support\Tenancy;

beforeEach(function (): void {
    $this->admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
    $this->owner = User::factory()->create();
    $this->business = Business::provision([
        'owner_user_id' => $this->owner->id,
        'name' => 'First Diner',
    ]);

    Tenancy::actingAs($this->business->id, function () {
        $this->location = Location::factory()->create(['name' => 'Downtown Location']);
    });
});

test('a real GET resolves the screen and the admin shell', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.tenant-locations'))
        ->assertOk()
        ->assertSee('Internal Platform Console');
});

test('an Owner gets the measured status', function (): void {
    $this->actingAs($this->owner)
        ->get(route('admin.tenant-locations'))
        ->assertForbidden();
});

test('one tenant location renders its distinctive name, but the tenant business name is missing (FINDING)', function (): void {
    $html = Livewire\Livewire::actingAs($this->admin)
        ->test(TenantLocations::class, ['businessId' => $this->business->id])
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->html();

    expect($html)->toContain($this->location->name)
        ->and($html)->not->toContain($this->business->name);
});
