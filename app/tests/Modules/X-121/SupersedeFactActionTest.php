<?php

declare(strict_types=1);

namespace Tests\Modules\X121;

use App\Models\Business;
use App\Modules\X121\Actions\SupersedeFactAction;
use App\Modules\X121\Models\Fact;
use App\Support\Tenancy;
use Tests\TestCase;

final class SupersedeFactActionTest extends TestCase
{
    public function test_no_existing_fact_creates_version_1(): void
    {
        $business = Business::factory()->create();
        Tenancy::set($business->id);

        $action = new SupersedeFactAction;
        $res = $action->handle(
            $business->id,
            'my_key',
            json_encode(['field' => 'val1']),
            'commit-123',
            'field',
            'val1'
        );

        $this->assertSame(['facts_created' => 1, 'facts_invalidated' => 0], $res);

        $this->assertDatabaseHas('facts', [
            'business_id' => $business->id,
            'key' => 'my_key',
            'version' => 1,
            'is_valid' => true,
            'commit_id' => 'commit-123',
        ]);
    }

    public function test_existing_fact_comparison_field_changed_supersedes(): void
    {
        $business = Business::factory()->create();
        Tenancy::set($business->id);

        $fact = Fact::create([
            'business_id' => $business->id,
            'key' => 'my_key',
            'value' => json_encode(['field' => 'val1']),
            'version' => 1,
            'commit_id' => 'commit-old',
            'is_valid' => true,
        ]);

        $action = new SupersedeFactAction;
        $res = $action->handle(
            $business->id,
            'my_key',
            json_encode(['field' => 'val2']),
            'commit-new',
            'field',
            'val2'
        );

        $this->assertSame(['facts_created' => 1, 'facts_invalidated' => 1], $res);

        // Old fact invalid
        $this->assertDatabaseHas('facts', [
            'id' => $fact->id,
            'is_valid' => false,
            'version' => 1,
        ]);

        // New fact created
        $this->assertDatabaseHas('facts', [
            'business_id' => $business->id,
            'key' => 'my_key',
            'version' => 2,
            'is_valid' => true,
            'commit_id' => 'commit-new',
        ]);
    }

    public function test_existing_fact_comparison_field_unchanged_does_nothing(): void
    {
        $business = Business::factory()->create();
        Tenancy::set($business->id);

        $fact = Fact::create([
            'business_id' => $business->id,
            'key' => 'my_key',
            'value' => json_encode(['field' => 'val1']),
            'version' => 1,
            'commit_id' => 'commit-old',
            'is_valid' => true,
        ]);

        $action = new SupersedeFactAction;
        $res = $action->handle(
            $business->id,
            'my_key',
            json_encode(['field' => 'val1']), // same value
            'commit-new',
            'field',
            'val1' // unchanged
        );

        $this->assertSame(['facts_created' => 0, 'facts_invalidated' => 0], $res);

        // No new row written, still exactly 1 row
        $this->assertDatabaseCount('facts', 1);

        // Still version 1 and valid
        $this->assertDatabaseHas('facts', [
            'id' => $fact->id,
            'version' => 1,
            'is_valid' => true,
            'commit_id' => 'commit-old',
        ]);
    }

    public function test_same_key_in_another_business_is_untouched(): void
    {
        $bizA = TestCase::provisionTenant(['name' => 'Biz A', 'currency' => 'USD']);
        $bizB = TestCase::provisionTenant(['name' => 'Biz B', 'currency' => 'USD']);

        // Seed fact for B
        Tenancy::set($bizB->id);
        $factB = Fact::create([
            'business_id' => $bizB->id,
            'key' => 'my_key',
            'value' => json_encode(['field' => 'valB']),
            'version' => 1,
            'commit_id' => 'commit-B',
            'is_valid' => true,
        ]);

        // Run action for A
        Tenancy::set($bizA->id);
        $action = new SupersedeFactAction;
        $res = $action->handle(
            $bizA->id,
            'my_key',
            json_encode(['field' => 'valA']),
            'commit-A',
            'field',
            'valA'
        );

        $this->assertSame(['facts_created' => 1, 'facts_invalidated' => 0], $res);

        // Verify B is untouched
        Tenancy::set($bizB->id);
        $this->assertDatabaseHas('facts', [
            'id' => $factB->id,
            'business_id' => $bizB->id,
            'version' => 1,
            'is_valid' => true,
            'commit_id' => 'commit-B',
        ]);
    }
}
