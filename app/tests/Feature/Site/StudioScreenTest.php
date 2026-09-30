<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Enums\UserRole;
use App\Livewire\Site\Studio;
use App\Models\User;
use App\Modules\X103\Models\Page;
use Livewire\Livewire;

test('the studio renders for a tenant owner', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $page = new Page;
    $page->business_id = $business->id;
    $page->title = 'SuperUniquePageTitle';
    $page->slug = 'home';
    $page->is_published = true;
    $page->save();

    $response = $this->actingAs($user)
        ->get(route('site.studio'))
        ->assertOk()
        ->assertSee('SuperUniquePageTitle')
        ->assertSee('sandbox="allow-scripts"', false)
        ->assertDontSee('allow-same-origin');

    // Assert nav item is rendered in the correct section (Reviews & your website)
    // The link is <a href="...site/studio">Site studio</a>.
    $response->assertSee('Site studio');
});

test('studio without tenant does not 500', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);

    // Not setting a tenant. Tenancy::idOrFail() will throw, which is handled (usually 400).
    $response = $this->actingAs($user)->get(route('site.studio'));
    expect($response->status())->toBe(400);
});

test('selecting a block sets the inspector and clears on page change', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $page1 = new Page;
    $page1->business_id = $business->id;
    $page1->title = 'Home';
    $page1->slug = 'home';
    $page1->is_published = true;
    $page1->draft_blocks = [['type' => 'hero'], ['type' => 'text']];
    $page1->save();

    $page2 = new Page;
    $page2->business_id = $business->id;
    $page2->title = 'About';
    $page2->slug = 'about';
    $page2->is_published = true;
    $page2->draft_blocks = [['type' => 'hero'], ['type' => 'text']];
    $page2->save();

    $this->actingAs($user);

    Livewire::test(Studio::class)
        ->set('pageId', $page1->id)
        ->call('selectBlock', 1)
        ->assertSet('selectedBlockIndex', 1)
        ->set('pageId', $page2->id)
        ->assertSet('selectedBlockIndex', null)
        ->assertSee('Select a block on the canvas.', false);
});

test('selectBlock guards against out of bounds index', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $page = new Page;
    $page->business_id = $business->id;
    $page->title = 'Home';
    $page->slug = 'home';
    $page->is_published = true;
    $page->draft_blocks = [['type' => 'hero'], ['type' => 'text']];
    $page->save();

    $this->actingAs($user);

    Livewire::test(Studio::class)
        ->set('pageId', $page->id)
        ->call('selectBlock', -1)
        ->assertSet('selectedBlockIndex', null)
        ->call('selectBlock', 2)
        ->assertSet('selectedBlockIndex', null)
        ->call('selectBlock', 1)
        ->assertSet('selectedBlockIndex', 1);
});

test('preview shows proposed changes with a marker', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $page = new Page;
    $page->business_id = $business->id;
    $page->title = 'Home';
    $page->slug = 'home';
    $page->is_published = true;
    $page->draft_blocks = [['type' => 'text', 'content' => 'Original Text']];
    $page->draft_meta = [
        'pending_edit' => [
            'blocks' => [['type' => 'text', 'content' => 'OstrichFeathers']],
        ],
    ];
    $page->save();

    $this->actingAs($user);

    Livewire::test(Studio::class)
        ->set('pageId', $page->id)
        ->assertSee('Previewing AI proposal')
        ->assertSee('OstrichFeathers');
});

test('the advanced website builder link reaches the studio', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $business->advanced_dashboard_enabled = true;
    $business->save();

    $this->actingAs($user)
        ->get(route('advanced.website-builder'))
        ->assertRedirect(route('site.studio'));
});
