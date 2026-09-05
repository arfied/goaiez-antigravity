<?php

use App\Models\Business;
use App\Models\User;
use App\Modules\X179\Models\TemplateMatch;
use App\Modules\X179\Ui\ProspecttenantfacingTop3Preview;
use App\Support\Tenancy;
use Livewire\Livewire;

it('forbids guest access to ProspecttenantfacingTop3Preview', function () {
    Livewire::test(ProspecttenantfacingTop3Preview::class, ['prospectId' => 999])
        ->assertForbidden();
});

it('allows owner access to ProspecttenantfacingTop3Preview', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $owner->id]);
    Tenancy::set($business->id);
    Tenancy::setUser($owner->id);

    $prospectId = 999;
    TemplateMatch::forceCreate([
        'business_id' => $business->id,
        'prospect_id' => $prospectId, 'template_id' => 1, 'rendered_preview' => '...',

    ]);
    Livewire::actingAs($owner)
        ->test(ProspecttenantfacingTop3Preview::class, ['prospectId' => $prospectId])
        ->assertOk();
});
