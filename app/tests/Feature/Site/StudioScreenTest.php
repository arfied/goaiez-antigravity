<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Enums\UserRole;
use App\Exceptions\TenantNotResolved;
use App\Livewire\Site\Studio;
use App\Models\User;
use App\Modules\X103\Models\Page;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

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
    $business = TestCase::provisionTenant([
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

    Livewire::test(Studio::class)
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
    $business = TestCase::provisionTenant([
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

    Http::fake([
        'api.openai.com/*' => Http::response([
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

    Livewire::test(Studio::class)
        ->set('pageId', $page->id)
        ->set('request', 'on the hero, set the headline and the subline')
        ->call('ask')
        ->assertSet('error', null);

    $page->refresh();
    expect($page->draft_meta['pending_edit']['blocks'][0]['headline'])->toBe('Asul and Blue');
    expect($page->draft_meta['pending_edit']['blocks'][0]['subline'])->toBe('Soft serve and milk tea, made to order');
});

test('the page picker is a dropdown above the preview and the left page list is gone', function () {
    // Fixture taken from the test above: an owner, a business and two pages.
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant(['owner_user_id' => $user->id, 'name' => 'Studio Test Business']);
    $home = Page::create(['business_id' => $business->id, 'title' => 'Home 7741', 'slug' => 'home', 'is_published' => true, 'draft_blocks' => [['type' => 'hero', 'headline' => 'H']]]);
    $about = Page::create(['business_id' => $business->id, 'title' => 'About 7742', 'slug' => 'about', 'is_published' => true, 'draft_blocks' => [['type' => 'hero', 'headline' => 'A']]]);
    $this->actingAs($user);

    $html = Livewire::test(Studio::class)->set('pageId', $about->id)->html();

    expect($html)->toContain('<select id="studio-page"')
        ->and($html)->toMatch('/<option value="'.$about->id.'" selected[^>]*>About 7742<\/option>/')
        ->and($html)->toMatch('/<option value="'.$home->id.'"\s*>Home 7741<\/option>/')
        ->and($html)->not->toContain('w-48 shrink-0 border-r');
});

test('while an AI design runs the studio says so and keeps checking, and stops when it is done', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant(['owner_user_id' => $user->id, 'name' => 'Studio Test Business']);
    $home = Page::create(['business_id' => $business->id, 'title' => 'Home', 'slug' => 'home', 'is_published' => true,
        'draft_blocks' => [['type' => 'hero', 'headline' => 'H']], 'draft_meta' => ['designs' => ['claude' => ['status' => 'running']]]]);
    $about = Page::create(['business_id' => $business->id, 'title' => 'About', 'slug' => 'about', 'is_published' => true,
        'draft_blocks' => [['type' => 'hero', 'headline' => 'A']]]);
    $this->actingAs($user);

    $designing = Livewire::test(Studio::class)->set('pageId', $home->id)->html();
    expect($designing)->toContain('The AI is designing this page')->and($designing)->toContain('wire:poll.5s');

    $elsewhere = Livewire::test(Studio::class)->set('pageId', $about->id)->html();
    expect($elsewhere)->toContain('The AI is designing 1 of your pages')->and($elsewhere)->toContain('wire:poll.5s');

    $home->update(['draft_meta' => ['designs' => ['claude' => ['status' => 'ready', 'blocks' => [['type' => 'hero', 'headline' => 'D']]]]]]);
    $done = Livewire::test(Studio::class)->set('pageId', $home->id)->html();
    expect($done)->not->toContain('The AI is designing')->and($done)->not->toContain('wire:poll');
});

test('the main buttons say what they are doing while they work', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant(['owner_user_id' => $user->id, 'name' => 'Studio Test Business']);
    $page = Page::create(['business_id' => $business->id, 'title' => 'Home', 'slug' => 'home', 'is_published' => false, 'draft_blocks' => [['type' => 'hero', 'headline' => 'H']]]);
    $this->actingAs($user);

    $html = Livewire::test(Studio::class)->set('pageId', $page->id)->html();

    expect($html)->toContain('<span wire:loading wire:target="ask">Asking the AI…</span>')
        ->and($html)->toContain('<span wire:loading wire:target="askDesign">Starting the designer…</span>')
        ->and($html)->toContain('Updating the preview…');
});
