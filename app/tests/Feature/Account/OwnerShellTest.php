<?php

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use Tests\Concerns\RefreshesTenantDatabase;

uses(RefreshesTenantDatabase::class);

test('the owner shell renders the sidebar, sign-out form and skip link', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = Business::provision([
        'owner_user_id' => $user->id,
        'name' => 'Acme Dental '.rand(100, 999),
    ]);

    $response = $this->actingAs($user)->get(route('account.home'));

    $response->assertOk();

    // the nav landmark is present
    $response->assertSee('aria-label="Your account"', false);

    // the Sign out control is a form with a CSRF field
    $response->assertSee('<form method="POST" action="'.route('logout').'">', false);
    $response->assertSee('<input type="hidden" name="_token"', false);

    // the skip link is present and targets #main
    $response->assertSee('href="#main"', false);
    $response->assertSee('Skip to content', false);

    // all five section headings render
    $response->assertSeeText('Customers');
    $response->assertSeeText('Messages & follow-ups');
    $response->assertSeeText('Reviews & your website');
    $response->assertSeeText('Phone & texting');
    $response->assertSeeText('Your account');

    // renders no link to a catalog-only route
    $response->assertDontSee(route('x-124.assistantunsupported-log'));
});
