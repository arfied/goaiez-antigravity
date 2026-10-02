<?php

namespace Tests\Modules\X103;

use App\Models\Business;
use App\Modules\X103\Actions\SiteEditAskAction;
use App\Modules\X103\Models\Page;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SiteEditAskActionTest extends TestCase
{
    private function provision(string $name): Business
    {
        $biz = TestCase::provisionTenant(['name' => $name, 'currency' => 'USD']);
        Tenancy::set($biz->id);

        return $biz;
    }

    private function fakeAiSuccess(string $explanation = 'Updated hero.'): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'msg_edit',
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'patches' => [
                            ['op' => 'set_string', 'block_index' => 0, 'field' => 'headline', 'value' => 'New Headline'],
                        ],
                        'explanation' => $explanation,
                    ])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ], 200, ['Content-Type' => 'application/json']),
        ]);
    }

    public function test_the_ask_action_proposes_patches_and_writes_a_pending_edit(): void
    {
        $biz = $this->provision('Ask Test 1');
        // from X103Test.php:779
        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Hero headline', 'subline' => 'Hero text', 'source' => 'crawl'],
                ['type' => 'about', 'text' => 'About text'],
            ],
            'is_published' => false,
        ]);

        $this->fakeAiSuccess();

        $action = app(SiteEditAskAction::class);
        $res = $action->handle($biz->id, $page->id, 'Change hero');

        $this->assertEquals('proposed', $res['status']);
        $this->assertEquals(1, $res['edits']);

        $page->refresh();
        $this->assertEquals('Hero headline', $page->draft_blocks[0]['headline']); // unchanged

        $pending = $page->draft_meta['pending_edit'];
        $this->assertNotNull($pending);
        $this->assertEquals('New Headline', $pending['blocks'][0]['headline']);
    }

    public function test_asking_again_appends_to_the_thread_and_keeps_the_first_entry(): void
    {
        $biz = $this->provision('Ask Test 2');
        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Hero headline', 'subline' => 'Hero text', 'source' => 'crawl'],
            ],
            'is_published' => false,
        ]);

        $this->fakeAiSuccess('First edit.');
        $action = app(SiteEditAskAction::class);
        $action->handle($biz->id, $page->id, 'Make it blue');

        $this->fakeAiSuccess('Second edit.');
        $action->handle($biz->id, $page->id, 'Make it red');

        $page->refresh();
        $pending = $page->draft_meta['pending_edit'];

        $this->assertCount(2, $pending['thread']);
        $this->assertEquals('Make it blue', $pending['thread'][0]['request']);
        $this->assertEquals('Make it red', $pending['thread'][1]['request']);
    }

    public function test_the_ask_action_refuses_and_leaves_the_draft_untouched(): void
    {
        $biz = $this->provision('Ask Test 3');
        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Hero headline'],
            ],
            'is_published' => false,
        ]);

        $action = app(SiteEditAskAction::class);
        // empty request triggers refused status in SiteEditProposeAction
        $res = $action->handle($biz->id, $page->id, '   ');

        $this->assertEquals('refused', $res['status']);
        $this->assertEquals('empty_request', $res['reason']);

        $page->refresh();
        $this->assertArrayNotHasKey('pending_edit', $page->draft_meta ?? []);
    }

    public function test_a_pre_thread_pending_edit_is_back_filled_on_the_next_ask(): void
    {
        $biz = $this->provision('Ask Test 4');
        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Hero headline'],
            ],
            'draft_meta' => [
                'pending_edit' => [
                    'request' => 'Old request',
                    'explanation' => 'Old explanation',
                    'drafted_at' => '2023-01-01T00:00:00Z',
                    'blocks' => [['type' => 'hero', 'headline' => 'Modified headline']],
                    'model' => 'old-model',
                ],
            ],
            'is_published' => false,
        ]);

        $this->fakeAiSuccess();

        $action = app(SiteEditAskAction::class);
        $action->handle($biz->id, $page->id, 'New request');

        $page->refresh();
        $pending = $page->draft_meta['pending_edit'];

        $this->assertCount(2, $pending['thread']);
        $this->assertEquals('Old request', $pending['thread'][0]['request']);
        $this->assertEquals('Old explanation', $pending['thread'][0]['explanation']);
        $this->assertEquals('2023-01-01T00:00:00Z', $pending['thread'][0]['at']);

        $this->assertEquals('New request', $pending['thread'][1]['request']);
    }

    private function fakeAiPatches(array ...$responses): void
    {
        $sequence = Http::sequence();
        foreach ($responses as $patches) {
            $sequence->push([
                'id' => 'msg_edit',
                'choices' => [['message' => ['content' => json_encode(['patches' => $patches, 'explanation' => 'Done.'])]]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ], 200, ['Content-Type' => 'application/json']);
        }
        Http::fake(['api.openai.com/*' => $sequence]);
    }

    public function test_a_follow_up_ask_keeps_the_edits_already_proposed(): void
    {
        $biz = $this->provision('Ask Test FollowUp');
        $page = Page::create([
            'business_id' => $biz->id, 'slug' => 'home', 'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Hero headline'],
                ['type' => 'about', 'text' => 'About text'],
            ],
            'is_published' => false,
        ]);

        $this->fakeAiPatches(
            [['op' => 'set_string', 'block_index' => 0, 'field' => 'headline', 'value' => 'First change']],
            [['op' => 'set_string', 'block_index' => 1, 'field' => 'text', 'value' => 'Second change']],
        );
        $action = app(SiteEditAskAction::class);
        $action->handle($biz->id, $page->id, 'Change the headline');
        $action->handle($biz->id, $page->id, 'Now the about text');

        $page->refresh();
        $pending = $page->draft_meta['pending_edit']['blocks'];
        $this->assertSame('First change', $pending[0]['headline']);
        $this->assertSame('Second change', $pending[1]['text']);
        $this->assertSame('Hero headline', $page->draft_blocks[0]['headline']);
    }

    public function test_a_section_ask_refuses_a_change_to_another_section(): void
    {
        $biz = $this->provision('Ask Test Scope');
        $page = Page::create([
            'business_id' => $biz->id, 'slug' => 'home', 'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Hero headline'],
                ['type' => 'about', 'text' => 'About text'],
            ],
            'is_published' => false,
        ]);

        $this->fakeAiPatches([['op' => 'set_string', 'block_index' => 0, 'field' => 'headline', 'value' => 'Out of scope']]);
        $res = app(SiteEditAskAction::class)->handle($biz->id, $page->id, 'Shorter', onlyBlock: 1);

        $this->assertSame('refused', $res['status']);
        $this->assertSame('outside_section', $res['reason']);
        $page->refresh();
        $this->assertNull($page->draft_meta['pending_edit'] ?? null);
    }

    public function test_a_section_ask_inside_its_section_is_proposed_and_names_the_section_to_the_ai(): void
    {
        $biz = $this->provision('Ask Test Scope Ok');
        $page = Page::create([
            'business_id' => $biz->id, 'slug' => 'home', 'title' => 'Home',
            'draft_blocks' => [
                ['type' => 'hero', 'headline' => 'Hero headline'],
                ['type' => 'about', 'text' => 'About text'],
            ],
            'is_published' => false,
        ]);

        $this->fakeAiPatches([['op' => 'set_string', 'block_index' => 1, 'field' => 'text', 'value' => 'Short.']]);
        $res = app(SiteEditAskAction::class)->handle($biz->id, $page->id, 'Shorter', onlyBlock: 1);

        $this->assertSame('proposed', $res['status']);
        Http::assertSent(fn ($request) => str_contains($request->body(), 'Only change block 1.'));
        $page->refresh();
        $this->assertSame('Short.', $page->draft_meta['pending_edit']['blocks'][1]['text']);
    }
}
