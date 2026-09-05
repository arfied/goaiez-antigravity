<?php

use App\Models\Business;
use App\Models\User;
use App\Modules\X142\Ui\ConnectYourAi;
use App\Support\Tenancy;
use Livewire\Livewire;

it('forbids guest access to ConnectYourAi', function () {
    Livewire::test(ConnectYourAi::class)
        ->assertForbidden();
});

it('allows owner access to ConnectYourAi', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $owner->id]);
    Tenancy::set($business->id);
    Tenancy::setUser($owner->id);

    Livewire::actingAs($owner)
        ->test(ConnectYourAi::class)
        ->assertOk();
});
