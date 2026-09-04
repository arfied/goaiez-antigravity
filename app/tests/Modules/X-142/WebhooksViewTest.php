<?php

use App\Models\Business;
use App\Models\User;
use App\Modules\X142\Ui\WebhooksView;
use App\Support\Tenancy;
use Livewire\Livewire;

it('forbids guest access to WebhooksView', function () {
    Livewire::test(WebhooksView::class)
        ->assertForbidden();
});

it('allows owner access to WebhooksView', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $owner->id]);
    Tenancy::set($business->id);
    Tenancy::setUser($owner->id);

    Livewire::actingAs($owner)
        ->test(WebhooksView::class)
        ->assertOk();
});
