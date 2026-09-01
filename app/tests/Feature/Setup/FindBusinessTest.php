<?php

declare(strict_types=1);

use App\Livewire\Setup\FindBusiness;
use Livewire\Livewire;
use App\Models\User;
use App\Models\Business;
use App\Support\Tenancy;

it('discards candidate on discardCandidate call', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $user->id]);
    Tenancy::set($business->id);
    Tenancy::setUser($user->id);
    \App\Models\WizardProgress::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'current_step' => \App\Enums\WizardStep::FindBusiness,
    ]);

    
    Livewire::actingAs($user)
        ->test(FindBusiness::class)
        ->call('discardCandidate')
        ->assertSet('candidate', null);
});
