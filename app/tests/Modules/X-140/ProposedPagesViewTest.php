<?php

use App\Models\Business;
use App\Models\User;
use App\Modules\X140\Ui\ProposedPagesView;
use App\Support\Tenancy;
use Livewire\Livewire;

it('forbids guest access to ProposedPagesView', function () {
    Livewire::test(ProposedPagesView::class)
        ->assertForbidden();
});

it('allows owner access to ProposedPagesView', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $owner->id]);
    Tenancy::set($business->id);
    Tenancy::setUser($owner->id);

    Livewire::actingAs($owner)
        ->test(ProposedPagesView::class)
        ->assertOk();
});
