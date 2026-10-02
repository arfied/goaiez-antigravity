<?php

declare(strict_types=1);

namespace Tests\Modules\X103\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Ui\Pages;
use App\Modules\X163\Models\PriceBookItem;
use App\Services\Facts\BusinessFactKey;
use App\Services\Facts\BusinessFacts;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PageEditorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_page_editor_displays_distinctive_content(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Page Editor Tenant', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Distinctive draft headline 5521'],
            ],
            'is_published' => false,
        ]);

        $this->actingAs($owner)->get(route('x-103.pages').'?edit='.$page->id)
            ->assertOk()
            ->assertSee('Editing Home')
            ->assertSee('srcdoc=')
            ->assertSee(htmlspecialchars('Distinctive draft headline 5521', ENT_QUOTES, 'UTF-8'));
    }

    public function test_page_editor_preview_toggles_proposed_and_draft(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Page Editor Tenant', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Distinctive draft 123'],
            ],
            'draft_meta' => [
                'pending_edit' => [
                    'explanation' => 'Test explanation', 'thread' => [],
                    'blocks' => [
                        ['type' => 'hero', 'headline' => 'Distinctive proposed 7713'],
                    ],
                ],
            ],
            'is_published' => false,
        ]);

        $lw = Livewire::actingAs($owner)
            ->test(Pages::class, ['editingPageId' => $page->id, 'previewProposed' => true]);

        $lw->assertViewHas('previewHtml', function ($html) {
            return str_contains((string) $html, 'Distinctive proposed 7713') && ! str_contains((string) $html, 'Distinctive draft 123');
        });

        $lw->call('showProposed', false);

        $lw->assertViewHas('previewHtml', function ($html) {
            return str_contains((string) $html, 'Distinctive draft 123') && ! str_contains((string) $html, 'Distinctive proposed 7713');
        });
    }

    public function test_page_editor_returns_404_for_other_business_page(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz1 = TestCase::provisionTenant(['name' => 'Biz 1', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $biz2 = TestCase::provisionTenant(['name' => 'Biz 2', 'currency' => 'USD']);
        Tenancy::set($biz2->id);

        $page = Page::create([
            'business_id' => $biz2->id,
            'slug' => 'home',
            'title' => 'Home',
            'is_published' => false,
        ]);

        Tenancy::set($biz1->id);

        $this->actingAs($owner)->get(route('x-103.pages').'?edit='.$page->id)
            ->assertNotFound();
    }

    public function test_page_editor_undo(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Undo Tenant', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $draftA = [['type' => 'hero', 'headline' => 'Draft A']];
        $draftB = [['type' => 'hero', 'headline' => 'Draft B']];

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => $draftA,
            'draft_meta' => [
                'pending_edit' => [
                    'explanation' => 'Test explanation', 'thread' => [],
                    'blocks' => $draftB,
                ],
            ],
            'is_published' => false,
        ]);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->call('applyEdit', $page->id);

        $page->refresh();
        $this->assertEquals($draftB, $page->draft_blocks);
        $this->assertCount(1, $page->draft_meta['undo']);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->call('undoEdit', $page->id)
            ->assertSet('success', 'Undone. Your draft is back to how it was before the last change.');

        $page->refresh();
        $this->assertEquals($draftA, $page->draft_blocks);
        $this->assertEmpty($page->draft_meta['undo']);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->call('undoEdit', $page->id)
            ->assertSet('error', 'Nothing to undo.');

        $staff = User::factory()->create(['role' => UserRole::Staff]);
        Livewire::actingAs($staff)
            ->test(Pages::class)
            ->assertForbidden();

        $manager = User::factory()->create(['role' => UserRole::Manager]);
        Livewire::actingAs($manager)
            ->test(Pages::class)
            ->call('undoEdit', $page->id)
            ->assertForbidden();
    }

    public function test_page_editor_undo_cap(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Undo Cap Tenant', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [],
            'is_published' => false,
        ]);

        $lw = Livewire::actingAs($owner)->test(Pages::class);

        for ($i = 0; $i < 25; $i++) {
            $page->refresh();
            $page->update([
                'draft_meta' => array_merge($page->draft_meta ?? [], [
                    'pending_edit' => [
                        'explanation' => 'Test explanation', 'thread' => [],
                        'blocks' => [['type' => 'hero', 'headline' => 'Edit '.$i]],
                    ],
                ]),
            ]);
            $lw->call('applyEdit', $page->id);
        }

        $page->refresh();
        $this->assertCount(20, $page->draft_meta['undo']);
    }

    public function test_page_editor_facts(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Facts Tenant', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [['type' => 'hero', 'headline' => 'Test']],
            'is_published' => false,
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response(
                json_encode([
                    'content' => [['type' => 'text', 'text' => json_encode(['blocks' => [], 'explanation' => ''])]],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                ]),
                200,
                ['Content-Type' => 'application/json']
            ),
            'api.openai.com/*' => Http::response([
                'id' => 'msg_edit',
                'choices' => [
                    ['message' => ['content' => json_encode(['blocks' => [], 'explanation' => ''])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ]),
        ]);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->set('editRequest.'.$page->id, 'request')
            ->call('askEdit', $page->id);

        Http::assertSent(fn ($r) => str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'Prices you may use: none — do not state any price.'));

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Service 1',
            'price_cents' => 10000,
            'is_confirmed' => true,
            'confirmed_at' => now(),
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response(
                json_encode([
                    'content' => [['type' => 'text', 'text' => json_encode(['blocks' => [], 'explanation' => ''])]],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                ]),
                200,
                ['Content-Type' => 'application/json']
            ),
            'api.openai.com/*' => Http::response([
                'id' => 'msg_edit2',
                'choices' => [
                    ['message' => ['content' => json_encode(['blocks' => [], 'explanation' => ''])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ]),
        ]);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->set('editRequest.'.$page->id, 'request')
            ->call('askEdit', $page->id);

        Http::assertSent(fn ($r) => str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'Prices you may use (never any other price):') && str_contains(json_encode($r->data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'Service 1'));
    }

    public function test_the_ai_editor_is_told_the_business_name_and_only_its_own_stated_facts(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $other = TestCase::provisionTenant(['name' => 'Another business 7750', 'currency' => 'USD']);
        app(BusinessFacts::class)->set($other->id, BusinessFactKey::TAGLINE, 'Another tenant tagline 7751');

        $biz = TestCase::provisionTenant(['name' => 'Editor facts 7752', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);
        app(BusinessFacts::class)->set($biz->id, BusinessFactKey::SERVICE_AREA, 'Distinctive service area 7753');

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [['type' => 'hero', 'headline' => 'Test']],
            'is_published' => false,
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response(
                json_encode([
                    'content' => [['type' => 'text', 'text' => json_encode(['blocks' => [], 'explanation' => ''])]],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                ]),
                200,
                ['Content-Type' => 'application/json']
            ),
            'api.openai.com/*' => Http::response([
                'id' => 'msg_edit',
                'choices' => [
                    ['message' => ['content' => json_encode(['blocks' => [], 'explanation' => ''])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ]),
        ]);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->set('editRequest.'.$page->id, 'mention where we work')
            ->call('askEdit', $page->id);

        $sent = fn ($r) => json_encode($r->data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        Http::assertSent(fn ($r) => str_contains($sent($r), 'Business name: Editor facts 7752')
            && str_contains($sent($r), 'Distinctive service area 7753')
            && str_contains($sent($r), 'the ONLY facts you may use'));
        Http::assertNotSent(fn ($r) => str_contains($sent($r), 'Another tenant tagline 7751')
            || str_contains($sent($r), 'Another business 7750'));
    }

    public function test_make_page_opens_in_editor(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Make Page Editor', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        Http::fake([
            'api.anthropic.com/*' => Http::response(
                json_encode([
                    'content' => [['type' => 'text', 'text' => json_encode(['title' => 'Distinctive spring offer 4471', 'slug' => 'Spring Offer!', 'blocks' => [['type' => 'hero', 'headline' => 'Distinctive headline 4472', 'subline' => 'Book before the rain.'], ['type' => 'faq', 'items' => [['question' => 'When?', 'answer' => 'All spring.']]], ['type' => 'marquee', 'text' => 'x']], 'explanation' => 'A page for the spring offer.'])]],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                ]),
                200,
                ['Content-Type' => 'application/json']
            ),
            'api.openai.com/*' => Http::response([
                'id' => 'msg_edit',
                'choices' => [
                    ['message' => ['content' => json_encode(['title' => 'Distinctive spring offer 4471', 'slug' => 'Spring Offer!', 'blocks' => [['type' => 'hero', 'headline' => 'Distinctive headline 4472', 'subline' => 'Book before the rain.'], ['type' => 'faq', 'items' => [['question' => 'When?', 'answer' => 'All spring.']]], ['type' => 'marquee', 'text' => 'x']], 'explanation' => 'A page for the spring offer.'])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ]),
        ]);

        $lw = Livewire::actingAs($owner)->test(Pages::class)
            ->set('pageRequest', 'make a page for our spring gutter offer')
            ->call('makePage');

        $page = Page::where('business_id', $biz->id)->where('slug', 'spring-offer')->first();
        if (! $page) {
            throw new \Exception('Page is null. Livewire error was: '.$lw->get('error'));
        }
        $lw->assertSet('editingPageId', $page->id);
    }
}
