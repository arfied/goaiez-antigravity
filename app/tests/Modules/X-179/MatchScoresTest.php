<?php

use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;
use App\Modules\X179\Ui\MatchScores;

it('forbids guest access to MatchScores', function () {
    Livewire::test(MatchScores::class, ['prospectId' => 999])
        ->assertForbidden();
});

it('allows owner access to MatchScores', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $owner->id]);
    Tenancy::set($business->id);
    Tenancy::setUser($owner->id);

    $prospectId = 999;
    \App\Modules\X179\Models\TemplateMatch::forceCreate([
        'business_id' => $business->id,
        'prospect_id' => $prospectId, 'template_id' => 1, 'rendered_preview' => '...',
        
    ]);
    Livewire::actingAs($owner)
        ->test(MatchScores::class, ['prospectId' => $prospectId])
        ->assertOk();
});
