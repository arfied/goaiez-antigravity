<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Enums\IndustryFamily;
use App\Models\IndustryStartingPoint;
use App\Modules\X103\Actions\SitePreviewAction;
use App\Modules\X103\Models\Page;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class SitePreviewActionTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_previews_rendered_for_each_variant(): void
    {
        $biz = self::provisionTenant([]);
        $biz->update(['industry' => \App\Enums\IndustryFamily::Trades->value]);

        IndustryStartingPoint::updateOrCreate(['family' => \App\Enums\IndustryFamily::Trades->value], [
            
            'palette' => ['surface' => '#ffffff', 'ink' => '#000000', 'primary' => '#ff0000', 'accent' => '#0000ff'],
            'type_pairing' => ['heading' => 'serif', 'body' => 'sans'],
            'section_order' => ['hero', 'about', 'gallery', 'reviews_strip', 'contact'],
        ]);

        $action = app(SitePreviewAction::class);
        $res = $action->handle($biz->id);
        $this->assertNull($res);

        Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [['type' => 'hero', 'headline' => 'Test Hero Headline']],
            'is_published' => false,
        ]);

        $res2 = $action->handle($biz->id);
        $this->assertIsArray($res2);
        $this->assertArrayHasKey('a', $res2);
        $this->assertArrayHasKey('b', $res2);
        $this->assertArrayHasKey('c', $res2);

        foreach (['a', 'b', 'c'] as $v) {
            $this->assertStringContainsString('<!doctype html>', $res2[$v]);
            $this->assertStringContainsString('Test Hero Headline', $res2[$v]);
        }

        $this->assertStringContainsString('--color-primary: #ff0000', $res2['a']);
        $this->assertStringContainsString('--color-accent: #ff0000', $res2['c']);
    }
}
