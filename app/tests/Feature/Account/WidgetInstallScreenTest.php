<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Location;
use App\Models\Plugin;
use App\Models\User;
use App\Support\Tenancy;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->business = Business::provision([
        'owner_user_id' => $this->owner->id,
        'name' => 'First Diner',
    ]);
    Tenancy::actingAs($this->business->id, function () {
        $this->location = Location::factory()->create();
        $this->plugin = Plugin::factory()->create(['location_id' => $this->location->id]);
    });
});

test('a GET request resolves the screen and its shell', function (): void {
    $this->actingAs($this->owner)
        ->get(route('account.website'))
        ->assertOk()
        ->assertSee('Your account')
        ->assertDontSee('Internal Platform Console');
});

test('the install snippet contains the instruction copy and the tenant identifier', function (): void {
    $this->actingAs($this->owner)
        ->get(route('account.website'))
        ->assertOk()
        ->assertSee('Put it wherever you want the reviews to appear')
        ->assertSee($this->plugin->embed_key);
});

test('it renders the heading', function (): void {
    $this->actingAs($this->owner)
        ->get(route('account.website'))
        ->assertOk()
        ->assertSee('Your reviews on your website');
});
