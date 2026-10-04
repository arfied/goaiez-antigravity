<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Modules\X103\Domain\BlockPatchApplier;
use App\Modules\X103\Domain\BlockPatchSchema;
use App\Modules\X103\Domain\SiteBlockRenderer;
use App\Modules\X103\Domain\SiteTemplates;
use App\Modules\X103\Domain\SiteThemes;
use App\Services\Industry\IndustryStartingPoints;
use Tests\TestCase;

/**
 * The products section (2026-10-04): a shop's own products, from the owner or the owner's existing store — never from the AI,
 * which may write the section's heading and nothing in its list, so it can never invent a product or a price.
 */
class ProductsSectionTest extends TestCase
{
    private const CONTEXT = [
        'businessName' => 'Kiln and Clay',
        'deployHash' => 'products',
        'tenant_storage_url_prefix' => '/m/',
        'form_action_base' => '/f',
    ];

    private function products(): array
    {
        return ['type' => 'products', 'heading' => 'New from the kiln', 'items' => [
            ['name' => 'Folk slip mug 7714', 'price_text' => '$38', 'description' => 'Holds 12 oz.', 'image_path' => 'tenant/9/mug.jpg', 'url' => 'https://shop.example.com/mug'],
            ['name' => 'Blue drip vase 7715', 'price_text' => '$88', 'url' => 'javascript:alert(1)'],
            ['name' => '', 'description' => 'ORPHAN_DESCRIPTION_3391'],
        ]];
    }

    private function render(array $blocks, array $extraTokens = []): string
    {
        $tokens = $extraTokens + app(IndustryStartingPoints::class)->for(null);

        return app(SiteBlockRenderer::class)->render($blocks, ['tokens' => $tokens] + self::CONTEXT);
    }

    public function test_a_products_section_renders_on_a_plain_page_and_under_every_theme(): void
    {
        $html = $this->render([$this->products()]);
        $this->assertStringContainsString('data-block-type="products"', $html);
        $this->assertStringContainsString('<h2>New from the kiln</h2>', $html);
        $this->assertStringContainsString('src="/m/mug.jpg"', $html);
        $this->assertStringContainsString('<a href="https://shop.example.com/mug">View Folk slip mug 7714</a>', $html);
        $this->assertStringContainsString('Blue drip vase 7715', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('ORPHAN_DESCRIPTION_3391', $html);

        foreach (array_keys(SiteThemes::THEMES) as $theme) {
            $this->assertStringContainsString('class="product-list"', $this->render([$this->products()], ['theme' => $theme]), $theme);
        }
    }

    public function test_the_shop_template_draws_each_product_and_a_tile_when_there_is_no_picture(): void
    {
        $template = SiteTemplates::TEMPLATES['maker-market'];
        $html = $this->render([['type' => 'hero', 'headline' => 'Pottery made by hand'], $this->products()], [
            'template' => 'maker-market', 'palette' => $template['palette'], 'type_pairing' => $template['type_pairing'],
        ]);

        $this->assertSame(2, substr_count($html, '<li class="mm-product">'));
        $this->assertStringContainsString('<a class="mm-product__link" href="https://shop.example.com/mug">', $html);
        $this->assertStringContainsString('<span aria-hidden="true">B</span>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('ORPHAN_DESCRIPTION_3391', $html);
        $this->assertStringContainsString('data-block-index="1" data-block-type="products"', $html);
    }

    public function test_the_ai_may_write_the_heading_but_never_the_products(): void
    {
        $this->assertNotContains('products', BlockPatchSchema::ITEM_TYPES);
        $this->assertNotContains('products', BlockPatchSchema::ADDABLE_TYPES);

        $blocks = [$this->products()];
        $applier = app(BlockPatchApplier::class);

        $rewrite = $applier->apply($blocks, [['op' => 'set_items', 'block_index' => 0, 'items' => [['name' => 'Invented teapot', 'price_text' => '$1']]]]);
        $this->assertSame('refused', $rewrite['status']);
        $this->assertSame($blocks, $rewrite['blocks']);

        $add = $applier->apply($blocks, [['op' => 'add_block', 'block_index' => 1, 'type' => 'products', 'fields' => ['heading' => 'More']]]);
        $this->assertSame('refused', $add['status']);

        $heading = $applier->apply($blocks, [['op' => 'set_string', 'block_index' => 0, 'field' => 'heading', 'value' => 'Fresh from the kiln 5521']]);
        $this->assertSame('applied', $heading['status']);
        $this->assertSame('Fresh from the kiln 5521', $heading['blocks'][0]['heading']);
        $this->assertSame($blocks[0]['items'], $heading['blocks'][0]['items']);
    }
}
