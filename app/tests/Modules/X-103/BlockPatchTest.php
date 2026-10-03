<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Modules\X103\Domain\BlockPatchApplier;
use App\Modules\X103\Domain\BlockPatchSchema;
use PHPUnit\Framework\TestCase;

final class BlockPatchTest extends TestCase
{
    public function test_every_object_in_the_schema_forbids_additional_properties(): void
    {
        $schema = BlockPatchSchema::schema();
        $this->assertObjectNodesForbidAdditionalProperties($schema);
    }

    private function assertObjectNodesForbidAdditionalProperties(array $node): void
    {
        if (isset($node['type']) && $node['type'] === 'object') {
            $this->assertArrayHasKey('additionalProperties', $node);
            $this->assertFalse($node['additionalProperties']);
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $this->assertObjectNodesForbidAdditionalProperties($value);
            }
        }
    }

    public function test_no_value_is_a_union_or_untyped(): void
    {
        $schema = BlockPatchSchema::schema();
        $this->assertTypesAreStringsAndNoOneOf($schema, true);
    }

    private function assertTypesAreStringsAndNoOneOf(array $node, bool $isRoot = false): void
    {
        $this->assertArrayNotHasKey('oneOf', $node, 'oneOf is not supported');

        // Check if this node has a property definition inside 'properties'
        if (isset($node['properties'])) {
            foreach ($node['properties'] as $propName => $propDef) {
                $this->assertArrayHasKey('type', $propDef, "Property $propName lacks a type");
                $this->assertIsString($propDef['type'], "Property $propName type must be a string");
            }
        }

        // Similarly for array items
        if (isset($node['items'])) {
            if (isset($node['items']['type'])) {
                $this->assertIsString($node['items']['type'], 'Items type must be a string');
            } else {
                $this->fail('Items definition lacks a type');
            }
        }

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $this->assertTypesAreStringsAndNoOneOf($value);
            }
        }
    }

    public function test_a_bad_index_refuses_the_whole_set_and_changes_nothing(): void
    {
        $applier = $applier = app(BlockPatchApplier::class);
        $blocks = [
            ['type' => 'hero', 'headline' => 'Old Title'],
            ['type' => 'text', 'content' => 'Hello'],
        ];

        $patches = [
            ['op' => 'set_string', 'block_index' => 0, 'field' => 'headline', 'value' => 'New Title'],
            ['op' => 'set_string', 'block_index' => 99, 'field' => 'content', 'value' => 'Bad Index'],
        ];

        $result = $applier->apply($blocks, $patches);

        $this->assertSame('refused', $result['status']);
        $this->assertSame(0, $result['applied']);
        $this->assertSame($blocks, $result['blocks']);
    }

    public function test_an_unknown_op_refuses_the_whole_set(): void
    {
        $applier = $applier = app(BlockPatchApplier::class);
        $blocks = [
            ['type' => 'hero', 'headline' => 'Old Title'],
        ];

        $patches = [
            ['op' => 'set_string', 'block_index' => 0, 'field' => 'headline', 'value' => 'New Title'],
            ['op' => 'unknown_op', 'block_index' => 0],
        ];

        $result = $applier->apply($blocks, $patches);

        $this->assertSame('refused', $result['status']);
        $this->assertSame(0, $result['applied']);
        $this->assertSame($blocks, $result['blocks']);
    }

    public function test_set_field_cannot_change_a_block_type(): void
    {
        $applier = $applier = app(BlockPatchApplier::class);
        $blocks = [
            ['type' => 'hero', 'headline' => 'Old Title'],
        ];

        $patches = [
            ['op' => 'set_string', 'block_index' => 0, 'field' => 'type', 'value' => 'text'],
        ];

        $result = $applier->apply($blocks, $patches);

        $this->assertSame('refused', $result['status']);
        $this->assertSame(0, $result['applied']);
        $this->assertSame($blocks, $result['blocks']);
    }

    public function test_the_sample_fixture_is_refused_because_it_writes_a_services_list(): void
    {
        $applier = $applier = app(BlockPatchApplier::class);
        $fixturePath = __DIR__.'/fixtures/patch-sample.json';
        $payload = json_decode(file_get_contents($fixturePath), true);

        $blocks = [
            ['type' => 'hero', 'headline' => 'Old Title'],
            ['type' => 'services', 'items' => []],
            ['type' => 'about', 'text' => 'Great!'],
            ['type' => 'booking_button', 'label' => '#'],
        ];

        $result = $applier->apply($blocks, $payload['patches']);

        $this->assertSame('refused', $result['status']);
        $this->assertSame('patch 1: unknown op', $result['reason']);
        $this->assertSame(0, $result['applied']);
        $this->assertSame($blocks, $result['blocks']);
    }

    public function test_patches_apply_in_the_order_given(): void
    {
        $applier = $applier = app(BlockPatchApplier::class);
        $blocks = [
            ['type' => 'hero', 'headline' => 'Old Title'],
        ];

        $patches = [
            ['op' => 'set_string', 'block_index' => 0, 'field' => 'headline', 'value' => 'First Title'],
            ['op' => 'set_string', 'block_index' => 0, 'field' => 'headline', 'value' => 'Second Title'],
        ];

        $result = $applier->apply($blocks, $patches);

        $this->assertSame('applied', $result['status']);
        $this->assertSame(2, $result['applied']);
        $this->assertSame('Second Title', $result['blocks'][0]['headline']);
    }

    public function test_add_block_inserts_an_about_section_at_the_given_index(): void
    {
        $applier = app(BlockPatchApplier::class);
        $blocks = [
            ['type' => 'hero', 'headline' => 'Old Title'],
            ['type' => 'about', 'text' => 'Hello'],
        ];

        $patches = [
            ['op' => 'add_block', 'block_index' => 1, 'type' => 'about', 'fields' => ['text' => 'We have painted North London since 2011.']],
        ];

        $result = $applier->apply($blocks, $patches);

        $this->assertSame('applied', $result['status']);
        $this->assertCount(3, $result['blocks']);
        $this->assertSame('about', $result['blocks'][1]['type']);
        $this->assertSame('We have painted North London since 2011.', $result['blocks'][1]['text']);
    }

    public function test_add_block_appends_when_the_index_equals_the_block_count(): void
    {
        $applier = app(BlockPatchApplier::class);
        $blocks = [
            ['type' => 'hero', 'headline' => 'Old Title'],
            ['type' => 'about', 'text' => 'Hello'],
        ];

        $patches = [
            ['op' => 'add_block', 'block_index' => 2, 'type' => 'about', 'fields' => ['text' => 'We have painted North London since 2011.']],
        ];

        $result = $applier->apply($blocks, $patches);

        $this->assertSame('applied', $result['status']);
        $this->assertCount(3, $result['blocks']);
        $this->assertSame('about', $result['blocks'][2]['type']);
    }

    public function test_add_block_refuses_an_index_past_the_end(): void
    {
        $applier = app(BlockPatchApplier::class);
        $blocks = [
            ['type' => 'hero', 'headline' => 'Old Title'],
            ['type' => 'about', 'text' => 'Hello'],
        ];

        $patches = [
            ['op' => 'add_block', 'block_index' => 3, 'type' => 'about', 'fields' => ['text' => 'We have painted North London since 2011.']],
        ];

        $result = $applier->apply($blocks, $patches);

        $this->assertSame('refused', $result['status']);
        $this->assertSame(0, $result['applied']);
        $this->assertSame($blocks, $result['blocks']);
        $this->assertStringContainsString('block_index out of range', $result['reason']);
    }

    public function test_add_block_adds_a_faq_with_a_question_and_an_answer(): void
    {
        $applier = app(BlockPatchApplier::class);
        $blocks = [
            ['type' => 'hero', 'headline' => 'Old Title'],
            ['type' => 'about', 'text' => 'Hello'],
        ];

        $patches = [
            ['op' => 'add_block', 'block_index' => 1, 'type' => 'faq', 'fields' => ['question' => 'Q1', 'answer' => 'A1']],
        ];

        $result = $applier->apply($blocks, $patches);

        $this->assertSame('applied', $result['status']);
    }

    public function test_add_block_adds_a_reviews_strip_with_its_items(): void
    {
        $applier = app(BlockPatchApplier::class);
        $blocks = [
            ['type' => 'hero', 'headline' => 'Old Title'],
            ['type' => 'about', 'text' => 'Hello'],
        ];

        $result = $applier->apply($blocks, [
            ['op' => 'add_block', 'block_index' => 1, 'type' => 'reviews_strip', 'fields' => ['heading' => 'What customers say'], 'items' => [['author' => 'Ann', 'text' => 'Great job', 'rating' => '5', 'image_path' => 'x.jpg']]],
        ]);

        $this->assertSame('applied', $result['status']);
        $this->assertSame(['type' => 'reviews_strip', 'heading' => 'What customers say', 'items' => [['author' => 'Ann', 'text' => 'Great job', 'rating' => '5']]], $result['blocks'][1]);
    }

    public function test_add_block_refuses_a_hero_with_an_empty_headline(): void
    {
        $applier = app(BlockPatchApplier::class);
        $blocks = [
            ['type' => 'hero', 'headline' => 'Old Title'],
            ['type' => 'about', 'text' => 'Hello'],
        ];

        $patches = [
            ['op' => 'add_block', 'block_index' => 1, 'type' => 'hero', 'fields' => ['headline' => '  ']],
        ];

        $result = $applier->apply($blocks, $patches);

        $this->assertSame('refused', $result['status']);
        $this->assertSame($blocks, $result['blocks']);
    }

    public function test_add_block_refuses_when_fields_are_missing(): void
    {
        $applier = app(BlockPatchApplier::class);
        $blocks = [
            ['type' => 'hero', 'headline' => 'Old Title'],
            ['type' => 'about', 'text' => 'Hello'],
        ];

        $patches = [
            ['op' => 'add_block', 'block_index' => 1, 'type' => 'about'],
        ];

        $result = $applier->apply($blocks, $patches);

        $this->assertSame('refused', $result['status']);
        $this->assertStringContainsString('requires fields', $result['reason']);
    }

    public function test_set_string_writes_any_page_field_but_never_a_file_path_or_an_unsafe_link(): void
    {
        $applier = app(BlockPatchApplier::class);
        $blocks = [
            ['type' => 'hero', 'headline' => 'H'],
            ['type' => 'booking_button', 'label' => 'Book', 'url' => 'https://example.com/book'],
            ['type' => 'contact', 'phone' => '0100'],
        ];

        $ok = $applier->apply($blocks, [
            ['op' => 'set_string', 'block_index' => 1, 'field' => 'url', 'value' => 'https://example.com/new'],
            ['op' => 'set_string', 'block_index' => 2, 'field' => 'phone', 'value' => '020 7946 0000'],
        ]);
        $this->assertSame('applied', $ok['status']);
        $this->assertSame('https://example.com/new', $ok['blocks'][1]['url']);
        $this->assertSame('020 7946 0000', $ok['blocks'][2]['phone']);

        foreach ([[1, 'url', 'javascript:alert(1)'], [0, 'image_path', 'some/other/file.jpg'], [0, 'items', 'x'], [0, 'image_width', '9']] as [$index, $field, $value]) {
            $res = $applier->apply($blocks, [['op' => 'set_string', 'block_index' => $index, 'field' => $field, 'value' => $value]]);
            $this->assertSame('refused', $res['status'], $field);
            $this->assertSame($blocks, $res['blocks'], $field);
        }
    }

    public function test_set_image_writes_only_a_hero_picture_path(): void
    {
        $applier = app(BlockPatchApplier::class);
        $blocks = [['type' => 'hero', 'headline' => 'H']];

        $ok = $applier->apply($blocks, [['op' => 'set_image', 'block_index' => 0, 'field' => 'image_path', 'path' => 'images/hero.jpg']]);
        $this->assertSame('applied', $ok['status']);
        $this->assertSame('images/hero.jpg', $ok['blocks'][0]['image_path']);

        $bad = $applier->apply($blocks, [['op' => 'set_image', 'block_index' => 0, 'field' => 'url', 'path' => 'x']]);
        $this->assertSame('refused', $bad['status']);
    }

    public function test_set_item_string_writes_only_a_list_faq_items_text(): void
    {
        $applier = app(BlockPatchApplier::class);
        $blocks = [
            ['type' => 'faq', 'items' => [['question' => 'Q?', 'answer' => 'A.']]],
            ['type' => 'services', 'items' => [['name' => 'Roof repair']]],
        ];

        $ok = $applier->apply($blocks, [['op' => 'set_item_string', 'block_index' => 0, 'item_index' => 0, 'field' => 'answer', 'value' => 'New.']]);
        $this->assertSame('applied', $ok['status']);
        $this->assertSame('New.', $ok['blocks'][0]['items'][0]['answer']);

        foreach ([[1, 0, 'name'], [0, 3, 'answer'], [0, 0, 'url']] as [$block, $item, $field]) {
            $res = $applier->apply($blocks, [['op' => 'set_item_string', 'block_index' => $block, 'item_index' => $item, 'field' => $field, 'value' => 'x']]);
            $this->assertSame('refused', $res['status'], "$block/$item/$field");
            $this->assertSame($blocks, $res['blocks']);
        }
    }

    public function test_set_items_writes_a_list_but_never_a_gallery_or_a_file_path(): void
    {
        $applier = app(BlockPatchApplier::class);
        $blocks = [
            ['type' => 'services', 'heading' => 'Services', 'items' => [['name' => 'Old']]],
            ['type' => 'gallery', 'items' => [['image_path' => 'a.jpg']]],
        ];

        $ok = $applier->apply($blocks, [['op' => 'set_items', 'block_index' => 0, 'items' => [['name' => 'Roof repair', 'price_text' => 'from £120', 'image_path' => 'x.jpg']]]]);
        $this->assertSame('applied', $ok['status']);
        $this->assertSame([['name' => 'Roof repair', 'price_text' => 'from £120']], $ok['blocks'][0]['items']);

        $gallery = $applier->apply($blocks, [['op' => 'set_items', 'block_index' => 1, 'items' => [['name' => 'x']]]]);
        $this->assertSame('refused', $gallery['status']);
        $this->assertSame($blocks, $gallery['blocks']);
    }

    public function test_the_ai_may_pick_a_hero_layout_and_button_but_never_an_unknown_layout_or_an_unsafe_button(): void
    {
        $applier = app(BlockPatchApplier::class);
        $blocks = [['type' => 'hero', 'headline' => 'H']];

        $ok = $applier->apply($blocks, [
            ['op' => 'set_string', 'block_index' => 0, 'field' => 'variant', 'value' => 'cover'],
            ['op' => 'set_string', 'block_index' => 0, 'field' => 'cta_url', 'value' => 'tel:+15550100'],
            ['op' => 'add_block', 'block_index' => 1, 'type' => 'stats', 'fields' => [], 'items' => [['value' => '15+', 'label' => 'years']]],
        ]);
        $this->assertSame('applied', $ok['status']);
        $this->assertSame('cover', $ok['blocks'][0]['variant']);
        $this->assertSame('tel:+15550100', $ok['blocks'][0]['cta_url']);
        $this->assertSame([['value' => '15+', 'label' => 'years']], $ok['blocks'][1]['items']);

        foreach ([['variant', 'fancy'], ['cta_url', 'javascript:alert(1)']] as [$field, $value]) {
            $res = $applier->apply($blocks, [['op' => 'set_string', 'block_index' => 0, 'field' => $field, 'value' => $value]]);
            $this->assertSame('refused', $res['status'], $field);
            $this->assertSame($blocks, $res['blocks'], $field);
        }
    }

    public function test_a_layout_must_belong_to_its_section(): void
    {
        $kept = BlockPatchSchema::modelBlock('services', ['heading' => 'H', 'variant' => 'list'], [['name' => 'S']]);
        $this->assertSame('list', $kept['variant']);

        $dropped = BlockPatchSchema::modelBlock('services', ['heading' => 'H', 'variant' => 'cover'], [['name' => 'S']]);
        $this->assertArrayNotHasKey('variant', $dropped);

        $applier = app(BlockPatchApplier::class);
        $blocks = [['type' => 'services', 'heading' => 'H', 'items' => [['name' => 'S']]]];
        $ok = $applier->apply($blocks, [['op' => 'set_string', 'block_index' => 0, 'field' => 'variant', 'value' => 'list']]);
        $this->assertSame('applied', $ok['status']);
        $this->assertSame('list', $ok['blocks'][0]['variant']);

        $bad = $applier->apply($blocks, [['op' => 'set_string', 'block_index' => 0, 'field' => 'variant', 'value' => 'zigzag']]);
        $this->assertSame('refused', $bad['status']);
    }
}
