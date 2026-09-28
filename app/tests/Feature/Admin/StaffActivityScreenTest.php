<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\User;
use App\Models\StaffEventRecord;
use App\Enums\UserRole;
use App\Enums\StaffEvent;

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

test('empty state from its blade; one activity row renders', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.audit-staff'))
        ->assertOk()
        ->assertSee('Nobody has been into a customer', false);
        
    StaffEventRecord::forceCreate([
        'actor' => 'user:' . $this->owner->id,
        'event' => StaffEvent::SignedIn,
        'subject_user_id' => $this->owner->id,
        'created_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.audit-staff'))
        ->assertOk()
        ->assertSee('Signed in');
});
