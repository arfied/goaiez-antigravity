<?php

use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;
use App\Modules\X102\Ui\OfflineFormInbox;

it('forbids guest access to OfflineFormInbox', function () {
    Livewire::test(OfflineFormInbox::class)
        ->assertForbidden();
});

it('allows owner access to OfflineFormInbox', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $owner->id]);
    Tenancy::set($business->id);
    Tenancy::setUser($owner->id);

    Livewire::actingAs($owner)
        ->test(OfflineFormInbox::class, )
        ->assertOk();
});
