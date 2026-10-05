<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Enums\UserRole;
use App\Livewire\Site\Studio;
use App\Models\User;
use App\Modules\X103\Actions\SiteDesignGenerateAction;
use App\Modules\X103\Jobs\SiteDesignJob;
use App\Modules\X103\Models\Page;
use App\Modules\X121\Models\Person;
use App\Modules\X155\Actions\FormReadAction;
use App\Modules\X155\Models\FormDefinition;
use App\Modules\X155\Models\FormSubmission;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The owner's whole road on a site template, in one test: pick a template in the Studio, ask the AI to fill it, see the proposal,
 * apply it, publish, open the live page, and a visitor's message through the page's own form becomes a lead. Every step has its
 * own tests; this one proves the steps join.
 */
class StudioTemplateJourneyTest extends TestCase
{
    public function test_pick_a_template_let_the_ai_fill_it_publish_and_a_visitor_reaches_the_business(): void
    {
        // Owner, business and page: fixture taken from StudioTemplatePickerTest::site(); the business's form and the draft's form
        // section exactly as SiteDraftAction builds them; the AI's answer as SiteDesignGenerateActionTest::fakeAnswer() fakes it.
        Storage::fake('local');
        // No step may reach a real vendor: an unexpected call fails the test instead.
        Http::preventStrayRequests();
        config(['credentials.anthropic_api_key' => 'fake-key']);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = TestCase::provisionTenant(['name' => 'Journey Plumbing 9950', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        $business->update(['industry' => 'trades']);
        $this->actingAs($owner);
        Tenancy::set($business->id);

        $form = FormDefinition::create([
            'business_id' => $business->id, 'form_name' => 'Contact', 'slug' => 'contact',
            'steps' => [['required' => ['first_name']]],
            'schema' => ['fields' => [
                ['name' => 'first_name', 'label' => 'Your name', 'type' => 'text'],
                ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel'],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                ['name' => 'message', 'label' => 'How can we help?', 'type' => 'textarea'],
            ]],
        ]);
        $definition = (new FormReadAction)->firstDefinitionForBusiness($business->id);
        $page = Page::create([
            'business_id' => $business->id, 'slug' => 'home', 'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Old headline'],
                ['type' => 'form', 'source' => 'forms', 'definition_id' => $definition['id'], 'fields' => $definition['fields'], 'required' => $definition['required'], 'honeypot' => $definition['honeypot']],
                ['type' => 'contact', 'phone' => '(253) 555-0150'],
            ],
            'is_published' => false,
        ]);

        // 1. Pick a template, then "Make it look great": on a template that queues the whole-page designer.
        $studio = Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->call('applyTemplate', 'trades-pro')
            ->assertSet('error', null)
            ->call('askDesign')
            ->assertSet('error', null);
        $this->assertStringStartsWith('Designing your page in the', (string) $studio->get('success'));

        // 2. The designer runs (here, by hand: the test queue holds the job) and its design goes up as the proposal.
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['blocks' => [
                ['type' => 'hero', 'headline' => 'Leaks fixed today 9951', 'subline' => 'Local plumbers since we opened.', 'cta_label' => 'Call us', 'cta_url' => 'tel:+12535550150'],
                ['type' => 'services', 'heading' => 'What we fix', 'items' => [['name' => 'Burst pipes 9952', 'description' => 'Found and fixed.']]],
                ['type' => 'contact', 'phone' => '(253) 555-0150'],
            ], 'explanation' => 'Filled the template.'])]],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ], 200, ['content-type' => 'application/json'])]);
        (new SiteDesignJob($business->id, $page->id, 'claude', proposeWhenReady: true))->handle(app(SiteDesignGenerateAction::class));
        $page->refresh();
        $this->assertArrayHasKey('pending_edit', $page->draft_meta);
        $this->assertSame('Old headline', $page->draft_blocks[0]['headline']);

        // 3. The owner sees it in the Studio and applies it; the business's own form section survives the AI's design.
        Livewire::test(Studio::class)
            ->set('pageId', $page->id)
            ->assertSee('Leaks fixed today 9951')
            ->call('applyProposal')
            ->assertSet('error', null);
        $page->refresh();
        $this->assertSame('Leaks fixed today 9951', $page->draft_blocks[0]['headline']);
        $this->assertContains('form', array_column($page->draft_blocks, 'type'));

        // 4. Publish (the deploy platform faked as StudioLoopTest fakes it), then open the live page.
        Http::fake(['api.vercel.com/*' => Http::response(['id' => 'dpl_9953', 'readyState' => 'READY', 'url' => 'example.vercel.app'])]);
        $published = Livewire::test(Studio::class)->set('pageId', $page->id)->call('publish', $page->id)->assertSet('error', null);
        $success = (string) $published->get('success');
        $this->assertStringContainsString('is live at', $success);
        $this->assertSame(1, preg_match('#(/sites/\d+/[^/\s]+)$#', $success, $m), $success);
        $live = (string) $this->get($m[1])->assertOk()->getContent();
        $this->assertStringContainsString('<div class="tp" id="top">', $live);
        $this->assertMatchesRegularExpression('/<h1[^>]*>Leaks fixed today 9951<\/h1>/', $live);
        $this->assertStringContainsString('Burst pipes 9952', $live);
        $this->assertSame(1, substr_count($live, '<form '));
        $this->assertStringContainsString('action="'.$m[1].'/forms/'.$form->id.'"', $live);

        // 5. A visitor writes in through that form, and the business has the lead.
        $this->post($m[1].'/forms/'.$form->id, [
            'first_name' => 'Rae 9954', 'phone' => '+15559990954', 'email' => 'rae9954@example.com', 'message' => 'Leak under the sink',
        ])->assertStatus(201);
        $this->assertSame(1, FormSubmission::where('business_id', $business->id)->where('form_definition_id', $form->id)->count());
        $this->assertNotNull(Person::where('business_id', $business->id)->where('phone', '+15559990954')->first());
    }
}
