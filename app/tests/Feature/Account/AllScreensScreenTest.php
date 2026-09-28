<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->business = Business::provision([
        'owner_user_id' => $this->owner->id,
        'name' => 'First Diner',
    ]);
});

test('a GET request resolves the screen and its shell', function (): void {
    $this->actingAs($this->owner)
        ->get(route('account.all-screens'))
        ->assertOk()
        ->assertSee('Your account')
        ->assertDontSee('Internal Platform Console');
});

test('the list includes the component labels', function (): void {
    $this->actingAs($this->owner)
        ->get(route('account.all-screens'))
        ->assertOk()
        ->assertSee('Things your assistant could not do');
});

test('a Staff user gets the status you measure', function (): void {
    $staff = User::factory()->withSecondFactor()->create(['role' => UserRole::SupportLead]);

    $this->actingAs($staff)
        ->get(route('account.all-screens'))
        ->assertForbidden();
});
