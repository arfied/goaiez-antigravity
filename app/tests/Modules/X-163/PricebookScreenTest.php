<?php

declare(strict_types=1);

namespace Tests\Modules\X163;

use App\Models\User;
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
}
