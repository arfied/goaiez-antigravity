<?php

declare(strict_types=1);

namespace Tests\Modules\X163;

use App\Models\User;
use App\Modules\X163\Domain\PricebookEngine;
use App\Modules\X163\Models\PriceBookItem;
use App\Modules\X163\Ui\Pricebook;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class PricebookScreenTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(Pricebook::class)->assertForbidden();
    }

    public function test_fresh_tenant_renders_zero_rows(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)
            ->test(Pricebook::class)
            ->assertOk()
            ->assertSee('Add your first price');

        $this->assertEquals(0, PriceBookItem::where('business_id', $biz->id)->count());
    }

    public function test_add_item_stores_cents(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)
            ->test(Pricebook::class)
            ->set('newServiceName', 'Test Service')
            ->set('newPriceDollars', 150)
            ->call('addItem');

        $this->assertDatabaseHas('price_book_items', [
            'business_id' => $biz->id,
            'service_name' => 'Test Service',
            'price_cents' => 15000,
        ]);
    }

    public function test_sample_row_shows_pill(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Sample Service',
            'price_cents' => 10000,
            'is_sample' => true,
            'is_confirmed' => false,
            'tax_rate_pct' => 0.0,
        ]);

        Livewire::actingAs($owner)
            ->test(Pricebook::class)
            ->assertSee('Sample Service')
            ->assertSee('Sample');
    }

    public function test_seeded_row_reaches_the_page(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Pricebook Seam',
            'price_cents' => 94225,
            'is_sample' => false,
            'is_confirmed' => false,
        ]);

        $this->actingAs($owner);
        $this->get(route('x-163.pricebook'))->assertOk()->assertSee('942.25');
    }

    public function test_inline_edit_unconfirms_a_confirmed_row(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $row = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Confirm Test 1',
            'price_cents' => 10000,
            'is_sample' => false,
            'is_confirmed' => true,
            'confirmed_at' => now()->subDay(),
            'tax_rate_pct' => 0.0,
        ]);

        Livewire::actingAs($owner)
            ->test(Pricebook::class)
            ->set('inlinePrices.'.$row->id, 200.00)
            ->call('updatePrice', $row->id);

        $reloaded = $row->fresh();
        $this->assertFalse($reloaded->is_confirmed);
        $this->assertNull($reloaded->confirmed_at);
    }

    public function test_inline_edit_with_the_same_amount_leaves_confirmation_alone(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $row = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Confirm Test 2',
            'price_cents' => 30000,
            'is_sample' => false,
            'is_confirmed' => true,
            'confirmed_at' => now()->subDay(),
            'tax_rate_pct' => 0.0,
        ]);

        Livewire::actingAs($owner)
            ->test(Pricebook::class)
            ->set('inlinePrices.'.$row->id, 300.00)
            ->call('updatePrice', $row->id);

        $reloaded = $row->fresh();
        $this->assertTrue($reloaded->is_confirmed);
        $this->assertEquals($row->confirmed_at, $reloaded->confirmed_at);
    }

    public function test_an_edited_price_is_no_longer_quoted_to_a_customer(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $row = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Confirm Test 3',
            'price_cents' => 40000,
            'is_sample' => false,
            'is_confirmed' => true,
            'confirmed_at' => now()->subDay(),
            'tax_rate_pct' => 0.0,
        ]);

        Livewire::actingAs($owner)
            ->test(Pricebook::class)
            ->set('inlinePrices.'.$row->id, 500.00)
            ->call('updatePrice', $row->id);

        $engine = app(PricebookEngine::class);
        $result = $engine->lookup($biz->id, 'Confirm Test 3', 'customer');

        $this->assertEquals('refused', $result['status']);
    }
}
