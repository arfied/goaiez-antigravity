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

    $this->actingAs($user)
        ->get(route('site.studio'))
        ->assertOk()
        ->assertSee('Your account');
});

test('selecting a block sets the inspector and clears on page change', function () {
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
    $page->draft_blocks = [['type' => 'hero'], ['type' => 'text'], ['type' => 'footer']];
    $page->save();

    $page2 = new Page;
    $page2->business_id = $business->id;
    $page2->title = 'About';
    $page2->slug = 'about';
    $page2->is_published = true;
    $page2->save();

    $this->actingAs($user);

    Livewire::test(Studio::class)
        ->set('pageId', $page->id)
        ->call('selectBlock', 2)
        ->assertSet('selectedBlockIndex', 2)
        ->assertSee('Block Index:', false)
        ->assertSee('2', false)
        ->set('pageId', $page2->id)
        ->assertSet('selectedBlockIndex', null);
});

test('the canvas iframe is sandboxed without same origin', function () {
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
    $page->save();

    $this->actingAs($user);

    Livewire::test(Studio::class)
        ->set('pageId', $page->id)
        ->assertSee('sandbox="allow-scripts"', false)
        ->assertDontSee('allow-same-origin');
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
