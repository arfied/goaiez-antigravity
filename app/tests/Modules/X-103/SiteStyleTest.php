<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Ui\Pages;
use App\Services\Industry\IndustryStartingPoints;
use App\Services\Industry\SiteStyle;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SiteStyleTest extends TestCase
{
    public function test_validate_drops_bad_values_and_checks_contrast(): void
    {
        $base = [
            'palette' => ['surface' => '#ffffff', 'ink' => '#000000'],
            'type_pairing' => ['heading' => 'sans-serif', 'body' => 'sans-serif'],
        ];

        // bad hex and unknown font dropped
        $res = SiteStyle::validate([
            'palette' => ['primary' => 'red', 'card' => '#123'],
            'type_pairing' => ['heading' => 'Comic Sans'],
        ], $base);
        $this->assertFalse($res['ok']);
        $this->assertEquals('No usable colour or font in that change.', $res['reason']);

        // low contrast refused
        $res = SiteStyle::validate([
            'palette' => ['surface' => '#888888', 'ink' => '#777777'],
        ], $base);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('That colour change would make text hard to read', $res['reason']);
        $this->assertStringContainsString('needs 4.5:1', $res['reason']);

        // good contrast passes
        $res = SiteStyle::validate([
            'palette' => ['surface' => '#ffffff', 'ink' => '#111111'],
        ], $base);
        $this->assertTrue($res['ok']);
        $this->assertEquals('#ffffff', $res['style']['palette']['surface']);
        $this->assertEquals('#111111', $res['style']['palette']['ink']);

        $contrast = SiteStyle::contrast('#000000', '#ffffff');
        $this->assertEqualsWithDelta(21.0, $contrast, 0.01);
    }

    public function test_for_business_returns_merged_site_tokens(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Test', 'currency' => 'USD', 'owner_user_id' => $owner->id]);

        Business::whereKey($biz->id)->update([
            'site_tokens' => ['palette' => ['primary' => '#aa3300']],
        ]);

        $sp = app(IndustryStartingPoints::class)->forBusiness($biz->id);
        $this->assertEquals('#aa3300', $sp['palette']['primary']);
    }

    public function test_ai_response_sets_pending_edit_style_and_preview_html(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Test', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [['type' => 'hero', 'headline' => 'Test']],
            'is_published' => false,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'msg_edit',
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'blocks' => [],
                        'explanation' => 'Changed style',
                        'style' => [
                            'palette' => ['primary' => '#c2410c'],
                            'type_pairing' => ['heading' => 'Georgia, serif'],
                        ],
                    ])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ]),
        ]);

        $lw = Livewire::actingAs($owner)
            ->test(Pages::class)
            ->set('editRequest.'.$page->id, 'request')
            ->call('askEdit', $page->id);

        $page->refresh();
        $this->assertNotNull($page->draft_meta['pending_edit']['style'] ?? null);
        $this->assertEquals('#c2410c', $page->draft_meta['pending_edit']['style']['palette']['primary']);

        $lw->set('editingPageId', $page->id);
        $lw->assertViewHas('previewHtml', function ($html) {
            return str_contains((string) $html, '--color-primary: #c2410c') && str_contains((string) $html, '--font-heading: Georgia, serif');
        });
    }

    public function test_apply_edit_and_undo_edit_with_style(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Test', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [['type' => 'hero', 'headline' => 'Test']],
            'draft_meta' => [
                'pending_edit' => [
                    'blocks' => [['type' => 'hero', 'headline' => 'Test']],
                    'explanation' => 'Test',
                    'style' => [
                        'palette' => ['primary' => '#c2410c'],
                    ],
                ],
            ],
            'is_published' => false,
        ]);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->call('applyEdit', $page->id);

        $tokens = Business::whereKey($biz->id)->value('site_tokens');
        if (is_string($tokens)) {
            $tokens = json_decode($tokens, true);
        }
        $this->assertEquals('#c2410c', $tokens['palette']['primary'] ?? null);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->call('undoEdit', $page->id);

        $tokens = Business::whereKey($biz->id)->value('site_tokens');
        $this->assertNull($tokens);

        // Test SB1-shaped undo
        $page->refresh();
        $page->draft_meta = [
            'undo' => [
                [['type' => 'hero', 'headline' => 'Old']],
            ],
        ];
        $page->save();

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->call('undoEdit', $page->id);

        $page->refresh();
        $this->assertEquals('Old', $page->draft_blocks[0]['headline']);
    }

    public function test_style_refused_when_ink_equals_surface(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = TestCase::provisionTenant(['name' => 'Test', 'currency' => 'USD', 'owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [['type' => 'hero']],
            'is_published' => false,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'msg_edit',
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'blocks' => [['type' => 'hero', 'headline' => 'Test']],
                        'explanation' => 'Changed style',
                        'style' => [
                            'palette' => ['surface' => '#ffffff', 'ink' => '#ffffff'],
                        ],
                    ])]],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
            ]),
        ]);

        Livewire::actingAs($owner)
            ->test(Pages::class)
            ->set('editRequest.'.$page->id, 'request')
            ->call('askEdit', $page->id);

        $page->refresh();
        $this->assertNull($page->draft_meta['pending_edit']['style'] ?? null);
        $this->assertNotNull($page->draft_meta['pending_edit']['style_refused'] ?? null);

        $tokens = Business::whereKey($biz->id)->value('site_tokens');
        $this->assertNull($tokens);
    }

    public function test_font_value_containing_quotes_is_dropped(): void
    {
        $base = [
            'palette' => ['surface' => '#ffffff', 'ink' => '#000000'],
            'type_pairing' => ['heading' => 'sans-serif', 'body' => 'sans-serif'],
        ];

        $res = SiteStyle::validate([
            'type_pairing' => ['heading' => 'Georgia"; } body { display:none'],
        ], $base);

        $this->assertFalse($res['ok']);
        $this->assertEquals('No usable colour or font in that change.', $res['reason']);
    }
}
