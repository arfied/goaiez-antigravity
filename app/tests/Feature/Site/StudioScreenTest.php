<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Enums\UserRole;
use App\Exceptions\TenantNotResolved;
use App\Livewire\Site\Studio;
use App\Models\User;
use App\Modules\X103\Models\Page;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

test('the studio refuses a no-tenant request', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $this->actingAs($user);
    $this->withoutExceptionHandling();

    $refused = false;
    try {
        $this->get(route('site.studio'));
    } catch (TenantNotResolved $e) {
        $refused = true;
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
        $refused = true;
    }

    expect($refused)->toBeTrue();
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
    $page->draft_blocks = [['type' => 'hero', 'headline' => 'Original Text']];
    $page->draft_meta = [
        'pending_edit' => [
            'blocks' => [['type' => 'hero', 'headline' => 'OstrichFeathers']],
        ],
    ];
    $page->save();

    $this->actingAs($user);

    Livewire::test(Studio::class)
        ->set('pageId', $page->id)
        ->assertSee('Previewing AI proposal')
        ->assertSee('OstrichFeathers')
        ->assertDontSee('Original Text');
});

test('the primary CTAs render with defined background tokens', function () {
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
    $page->draft_blocks = [['type' => 'hero', 'headline' => 'Original Text']];
    $page->draft_meta = [
        'pending_edit' => [
            'blocks' => [['type' => 'hero', 'headline' => 'OstrichFeathers']],
        ],
    ];
    $page->save();

    $this->actingAs($user)
        ->get(route('site.studio'))
        ->assertOk()
        ->assertSee('Apply')
        ->assertSee('Ask')
        ->assertDontSee('bg-brand')
        ->assertSee('bg-ink', false)
        ->assertSee('class="w-full min-h-screen border border-rule bg-canvas"', false);
});

test('the preview is a centered page and the clicked block carries the outline attribute', function () {
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
    $page->draft_blocks = [['type' => 'hero', 'headline' => 'Asul and Blue'], ['type' => 'about', 'text' => 'x']];
    $page->save();

    $this->actingAs($user);

    Livewire::test(Studio::class)
        ->set('pageId', $page->id)
        ->assertSee('data-preview-page', false)
        ->assertDontSee('data-selected-block', false)
        ->call('selectBlock', 0)
        ->assertSet('selectedBlockIndex', 0)
        ->assertSee('data-selected-block=&quot;0&quot;', false);
});

test('ask on an empty page refuses with a sentence and opens no proposal', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = \Tests\TestCase::provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $page = new Page;
    $page->business_id = $business->id;
    $page->title = 'Home';
    $page->slug = 'home';
    $page->is_published = true;
    $page->draft_blocks = [];
    $page->save();

    $this->actingAs($user);

    \Livewire\Livewire::test(Studio::class)
        ->set('pageId', $page->id)
        ->set('request', 'rewrite this page')
        ->call('ask')
        ->assertSet('success', null)
        ->assertSee('This page has no blocks yet. Add a hero first.');

    $page->refresh();
    expect($page->draft_meta['pending_edit'] ?? null)->toBeNull();
});

test('ask that sets the headline and the subline on a hero returns a proposal carrying both', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = \Tests\TestCase::provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business 2',
    ]);

    $page = new Page;
    $page->business_id = $business->id;
    $page->title = 'Home';
    $page->slug = 'home';
    $page->is_published = true;
    $page->draft_blocks = [['type' => 'hero', 'headline' => 'old', 'subline' => 'old']];
    $page->save();

    \Illuminate\Support\Facades\Http::fake([
        'api.openai.com/*' => \Illuminate\Support\Facades\Http::response([
            'id' => 'msg_edit',
            'choices' => [
                ['message' => ['content' => json_encode([
                    'patches' => [
                        ['op' => 'set_string', 'block_index' => 0, 'field' => 'headline', 'value' => 'Asul and Blue'],
                        ['op' => 'set_string', 'block_index' => 0, 'field' => 'subline', 'value' => 'Soft serve and milk tea, made to order'],
                    ],
                    'explanation' => 'Updated hero.',
                ])]],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
        ], 200, ['Content-Type' => 'application/json']),
    ]);

    $this->actingAs($user);

    \Livewire\Livewire::test(Studio::class)
        ->set('pageId', $page->id)
        ->set('request', 'on the hero, set the headline and the subline')
        ->call('ask')
        ->assertSet('error', null);

    $page->refresh();
    expect($page->draft_meta['pending_edit']['blocks'][0]['headline'])->toBe('Asul and Blue');
    expect($page->draft_meta['pending_edit']['blocks'][0]['subline'])->toBe('Soft serve and milk tea, made to order');
});
