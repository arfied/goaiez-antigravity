<?php

declare(strict_types=1);

use App\Livewire\Advanced\Citations;
use App\Livewire\Account\Settings;
use Livewire\Livewire;
use App\Models\User;
use App\Models\Business;
use App\Support\Tenancy;
use App\Models\Citation;

it('Citations mount creates zero rows', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $user->id]);
    Tenancy::set($business->id);
    Tenancy::setUser($user->id);
    
    Livewire::actingAs($user)
        ->test(Citations::class);
        
    $this->assertEquals(0, Citation::where('business_id', $business->id)->count());
});

it('toggleAdvanced refused for non-owner', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $owner->id]);
    
    $nonOwner = User::factory()->create();
    // Simulate non-owner having access to the business (via team member perhaps, but Tenancy sets the context)
    Tenancy::set($business->id);
    Tenancy::setUser($nonOwner->id);
    
    Livewire::actingAs($nonOwner)
        ->test(Settings::class)
        ->call('toggleAdvanced')
        ->assertForbidden();
});
