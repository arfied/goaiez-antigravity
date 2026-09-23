<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
    $this->owner = User::factory()->create();
    $this->business = Business::provision([
        'owner_user_id' => $this->owner->id,
        'name' => 'First Diner',
    ]);
});

test('a real GET resolves the screen and the admin shell', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.audit-staff'))
        ->assertOk()
        ->assertSee('Internal Platform Console');
});

test('an Owner gets the measured status', function (): void {
    $this->actingAs($this->owner)
        ->get(route('admin.audit-staff'))
        ->assertForbidden();
});

test('empty state renders from its blade', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.audit-staff'))
        ->assertOk()
        ->assertSee('Nobody has been into a customer', false);
});
