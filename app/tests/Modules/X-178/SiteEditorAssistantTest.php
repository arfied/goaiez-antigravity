<?php

use App\Models\Business;
use App\Models\User;
use App\Modules\X178\Ui\SiteEditorAssistant;
use App\Support\Tenancy;
use App\Enums\UserRole;
use Livewire\Livewire;

it('forbids guest access to SiteEditorAssistant', function () {
    Livewire::test(SiteEditorAssistant::class)
        ->assertForbidden();
});

it('forbids a staff user with a tenant', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $owner->id]);
    $staff = User::factory()->role(UserRole::Staff)->create();
    Tenancy::set($business->id);
    Tenancy::setUser($staff->id);

    Livewire::actingAs($staff)
        ->test(SiteEditorAssistant::class)
        ->assertForbidden();
});

it('allows owner access to SiteEditorAssistant', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $owner->id]);
    Tenancy::set($business->id);
    Tenancy::setUser($owner->id);

    Livewire::actingAs($owner)
        ->test(SiteEditorAssistant::class)
        ->assertOk();
});
