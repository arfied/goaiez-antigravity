<?php

use App\Models\Business;
use App\Models\User;
use App\Modules\X192\Ui\MembershipsList;
use App\Support\Tenancy;
use Livewire\Livewire;

it('forbids guest access to MembershipsList', function () {
    Livewire::test(MembershipsList::class)
        ->assertForbidden();
});

it('allows owner access to MembershipsList', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $owner->id]);
    Tenancy::set($business->id);
    Tenancy::setUser($owner->id);

    Livewire::actingAs($owner)
        ->test(MembershipsList::class)
        ->assertOk();
});
