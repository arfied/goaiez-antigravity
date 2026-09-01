<?php

declare(strict_types=1);

use App\Enums\WizardStep;
use App\Livewire\Setup\FindBusiness;
use App\Models\Business;
use App\Models\User;
use App\Models\WizardProgress;
use App\Support\Tenancy;
use Livewire\Livewire;

it('discards candidate on discardCandidate call', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $user->id]);
    Tenancy::set($business->id);
    Tenancy::setUser($user->id);
    WizardProgress::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'current_step' => WizardStep::FindBusiness,
    ]);

    Livewire::actingAs($user)
        ->test(FindBusiness::class)
        ->call('discardCandidate')
        ->assertSet('candidate', null);
});
