<?php

namespace Tests\Modules\X103;

use App\Models\Business;
use App\Modules\X103\Actions\SiteEditApplyAction;
use App\Modules\X103\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteEditApplyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_apply_action_moves_the_proposal_into_the_draft_and_keeps_an_undo()
    {
        $biz = Business::factory()->create();
        // Fixture taken from app/tests/Modules/X-103/X103Test.php:2362
        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'faq',
            'title' => 'FAQ',
            'draft_blocks' => [
                ['type' => 'about', 'text' => 'Old text'],
            ],
            'is_published' => false,
        ]);

        $meta = $page->draft_meta ?? [];
        $meta['pending_edit'] = [
            'blocks' => [
                ['type' => 'about', 'text' => 'New text'],
            ],
        ];
        $page->draft_meta = $meta;
        $page->save();

        $action = new SiteEditApplyAction;
        $res = $action->handle($biz->id, $page->id);

        $this->assertEquals('applied', $res['status']);
        $this->assertFalse($res['applied_style']);

        $page->refresh();
        $this->assertEquals([
            ['type' => 'about', 'text' => 'New text'],
        ], $page->draft_blocks);

        $this->assertArrayNotHasKey('pending_edit', $page->draft_meta);
        $this->assertCount(1, $page->draft_meta['undo']);
        $this->assertEquals([
            ['type' => 'about', 'text' => 'Old text'],
        ], $page->draft_meta['undo'][0]['blocks']);
    }

    public function test_the_apply_action_refuses_when_nothing_is_proposed()
    {
        $biz = Business::factory()->create();
        // Fixture taken from app/tests/Modules/X-103/X103Test.php:2362
        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'faq',
            'title' => 'FAQ',
            'draft_blocks' => [
                ['type' => 'about', 'text' => 'Old text'],
            ],
            'is_published' => false,
        ]);

        $action = new SiteEditApplyAction;
        $res = $action->handle($biz->id, $page->id);

        $this->assertEquals('refused', $res['status']);
        $this->assertEquals('Nothing proposed.', $res['reason']);
        $this->assertFalse($res['applied_style']);

        $page->refresh();
        $this->assertEquals([
            ['type' => 'about', 'text' => 'Old text'],
        ], $page->draft_blocks);
    }

    public function test_applying_a_style_proposal_merges_the_tokens_and_says_so()
    {
        $biz = Business::factory()->create([
            'site_tokens' => [
                'palette' => ['primary' => '#000000', 'secondary' => '#ffffff'],
            ],
        ]);
        // Fixture taken from app/tests/Modules/X-103/X103Test.php:2362
        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'faq',
            'title' => 'FAQ',
            'draft_blocks' => [],
            'is_published' => false,
        ]);

        $meta = $page->draft_meta ?? [];
        $meta['pending_edit'] = [
            'blocks' => [],
            'style' => [
                'palette' => ['primary' => '#ff0000'],
            ],
        ];
        $page->draft_meta = $meta;
        $page->save();

        $action = new SiteEditApplyAction;
        $res = $action->handle($biz->id, $page->id);

        $this->assertEquals('applied', $res['status']);
        $this->assertTrue($res['applied_style']);

        $biz->refresh();
        $this->assertEquals([
            'palette' => ['primary' => '#ff0000', 'secondary' => '#ffffff'],
        ], $biz->site_tokens);

        $page->refresh();
        $this->assertEquals([
            'palette' => ['primary' => '#000000', 'secondary' => '#ffffff'],
        ], $page->draft_meta['undo'][0]['site_tokens']);
    }

    public function test_the_undo_stack_never_exceeds_twenty()
    {
        $biz = Business::factory()->create();
        // Fixture taken from app/tests/Modules/X-103/X103Test.php:2362
        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'faq',
            'title' => 'FAQ',
            'draft_blocks' => [],
            'is_published' => false,
        ]);

        $meta = $page->draft_meta ?? [];
        $meta['undo'] = array_fill(0, 20, ['blocks' => [['type' => 'old']]]);
        $meta['pending_edit'] = [
            'blocks' => [['type' => 'new']],
        ];
        $page->draft_meta = $meta;
        $page->save();

        $action = new SiteEditApplyAction;
        $res = $action->handle($biz->id, $page->id);

        $page->refresh();
        $this->assertCount(20, $page->draft_meta['undo']);
        // The oldest one should have been dropped.
        $this->assertEquals([], $page->draft_meta['undo'][19]['blocks']);
    }
}
