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
        $applier = new BlockPatchApplier;
        $blocks = [
            ['type' => 'hero', 'title' => 'Old Title'],
            ['type' => 'text', 'content' => 'Hello'],
        ];

        $patches = [
            ['op' => 'set_string', 'block_index' => 0, 'field' => 'title', 'value' => 'New Title'],
            ['op' => 'set_string', 'block_index' => 99, 'field' => 'content', 'value' => 'Bad Index'],
        ];

        $result = $applier->apply($blocks, $patches);

        $this->assertSame('refused', $result['status']);
        $this->assertSame(0, $result['applied']);
        $this->assertSame($blocks, $result['blocks']);
    }

    public function test_an_unknown_op_refuses_the_whole_set(): void
    {
        $applier = new BlockPatchApplier;
        $blocks = [
            ['type' => 'hero', 'title' => 'Old Title'],
        ];

        $patches = [
            ['op' => 'set_string', 'block_index' => 0, 'field' => 'title', 'value' => 'New Title'],
            ['op' => 'unknown_op', 'block_index' => 0],
        ];

        $result = $applier->apply($blocks, $patches);

        $this->assertSame('refused', $result['status']);
        $this->assertSame(0, $result['applied']);
        $this->assertSame($blocks, $result['blocks']);
    }

    public function test_set_field_cannot_change_a_block_type(): void
    {
        $applier = new BlockPatchApplier;
        $blocks = [
            ['type' => 'hero', 'title' => 'Old Title'],
        ];

        $patches = [
            ['op' => 'set_string', 'block_index' => 0, 'field' => 'type', 'value' => 'text'],
        ];

        $result = $applier->apply($blocks, $patches);

        $this->assertSame('refused', $result['status']);
        $this->assertSame(0, $result['applied']);
        $this->assertSame($blocks, $result['blocks']);
    }

    public function test_the_sample_fixture_applies(): void
    {
        $applier = new BlockPatchApplier;
        $fixturePath = __DIR__.'/fixtures/patch-sample.json';
        $payload = json_decode(file_get_contents($fixturePath), true);

        $blocks = [
            ['type' => 'hero', 'title' => 'Old Title'],
            ['type' => 'features', 'features' => []],
            ['type' => 'testimonial', 'text' => 'Great!'],
            ['type' => 'cta', 'link' => '#'],
        ];

        $result = $applier->apply($blocks, $payload['patches']);

        $this->assertSame('applied', $result['status']);
        $this->assertSame(4, $result['applied']);

        $expectedBlocks = [
            ['type' => 'hero', 'title' => 'Welcome to our updated platform'],
            ['type' => 'cta', 'link' => '#'],
            ['type' => 'features', 'features' => ['Faster', 'More secure', 'Easy to use']],
        ];

        $this->assertSame($expectedBlocks, $result['blocks']);
    }

    public function test_patches_apply_in_the_order_given(): void
    {
        $applier = new BlockPatchApplier;
        $blocks = [
            ['type' => 'hero', 'title' => 'Old Title'],
        ];

        $patches = [
            ['op' => 'set_string', 'block_index' => 0, 'field' => 'title', 'value' => 'First Title'],
            ['op' => 'set_string', 'block_index' => 0, 'field' => 'title', 'value' => 'Second Title'],
        ];

        $result = $applier->apply($blocks, $patches);

        $this->assertSame('applied', $result['status']);
        $this->assertSame(2, $result['applied']);
        $this->assertSame('Second Title', $result['blocks'][0]['title']);
    }
}
