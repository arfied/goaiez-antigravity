<?php

declare(strict_types=1);

namespace Tests\Modules\X167;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X167\Actions\StockAdjustAction;
use App\Modules\X167\Events\InventoryConsumed;
use App\Modules\X167\Events\PoSent;
use App\Modules\X167\Events\ReorderTriggered;
use App\Modules\X167\Events\StockLow;
use App\Modules\X167\Models\PurchaseOrder;
use App\Modules\X167\Models\StockItem;
use App\Modules\X167\Models\StockLocation;
use App\Modules\X167\Ui\StockByVan;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class StockByVanTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(StockByVan::class)->assertForbidden();
    }

    public function test_staff_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Staff;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(StockByVan::class)->assertForbidden();
    }

    public function test_empty_sentence(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(StockByVan::class)
            ->assertSee('No stock yet. Add your first item above and it appears here.');
    }

    public function test_seeded_items_show_in_van(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Event::fake([InventoryConsumed::class, ReorderTriggered::class, StockLow::class, PoSent::class]);

        $van = StockLocation::create([
            'business_id' => $biz->id,
            'name' => 'Van 04',
            'type' => 'van',
        ]);

        $item = StockItem::create([
            'business_id' => $biz->id,
            'location_id' => $van->id,
            'sku' => 'PIPE-10M',
            'barcode' => '123',
            'name' => 'Copper Pipe',
            'quantity' => 10.0,
            'unit' => 'm',
            'reorder_point' => 3.0,
        ]);

        app(StockAdjustAction::class)->handle(
            businessId: $biz->id,
            stockItemId: $item->id,
            quantityDelta: 2.5,
            isCancellation: false
        );

        Livewire::actingAs($user)->test(StockByVan::class)
            ->assertSee('Van 04')
            ->assertSee('Copper Pipe')
            ->assertSee('7.50 m')
            ->assertSee('In stock')
            ->assertDontSee('<span>Sample</span>', false);
    }

    public function test_item_low_stock(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Event::fake([InventoryConsumed::class, ReorderTriggered::class, StockLow::class, PoSent::class]);

        $van = StockLocation::create([
            'business_id' => $biz->id,
            'name' => 'Van 05',
            'type' => 'van',
        ]);

        $item = StockItem::create([
            'business_id' => $biz->id,
            'location_id' => $van->id,
            'sku' => 'PIPE-LOW',
            'barcode' => '1234',
            'name' => 'Short Pipe',
            'quantity' => 2.0,
            'unit' => 'm',
            'reorder_point' => 3.0,
        ]);

        Livewire::actingAs($user)->test(StockByVan::class)
            ->assertSee('Short Pipe')
            ->assertSee('<span>Low</span>', false);
    }

    public function test_propose_restock_creates_po(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Event::fake([InventoryConsumed::class, ReorderTriggered::class, StockLow::class, PoSent::class]);

        $van = StockLocation::create([
            'business_id' => $biz->id,
            'name' => 'Van 06',
            'type' => 'van',
        ]);

        $item = StockItem::create([
            'business_id' => $biz->id,
            'location_id' => $van->id,
            'sku' => 'PIPE-RESTOCK',
            'barcode' => '12345',
            'name' => 'Restock Pipe',
            'quantity' => 2.0,
            'unit' => 'm',
            'reorder_point' => 5.0,
        ]);

        $this->assertEquals(0, PurchaseOrder::where('business_id', $biz->id)->count());

        Livewire::actingAs($user)->test(StockByVan::class)
            ->call('proposeRestock', $item->id)
            ->assertSee('Proposed')
            ->assertSee('PO-');

        $this->assertEquals(1, PurchaseOrder::where('business_id', $biz->id)->count());
        $po = PurchaseOrder::where('business_id', $biz->id)->first();
        $this->assertEquals('proposed', $po->status);
        $this->assertEquals('PIPE-RESTOCK', $po->items[0]['sku']);
    }

    public function test_sample_pill_shown(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Event::fake([InventoryConsumed::class, ReorderTriggered::class, StockLow::class, PoSent::class]);

        $van = StockLocation::create([
            'business_id' => $biz->id,
            'name' => 'Van 07',
            'type' => 'van',
        ]);

        $item = StockItem::create([
            'business_id' => $biz->id,
            'location_id' => $van->id,
            'sku' => 'PIPE-SAMPLE',
            'barcode' => '123456',
            'name' => 'Sample Pipe',
            'quantity' => 2.0,
            'unit' => 'm',
            'reorder_point' => 5.0,
            'is_sample' => true,
        ]);

        Livewire::actingAs($user)->test(StockByVan::class)
            ->assertSee('<span>Sample</span>', false);
    }

    public function test_real_get_shows_derived_magnitude(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $van = StockLocation::create([
            'business_id' => $biz->id,
            'name' => 'Van 04',
            'type' => 'van',
        ]);

        StockItem::create([
            'business_id' => $biz->id,
            'location_id' => $van->id,
            'sku' => 'TEST-01',
            'barcode' => '789',
            'name' => 'Derived Magnitudes',
            'quantity' => 372.64,
            'unit' => 'm',
            'reorder_point' => 3.0,
        ]);

        $this->actingAs($user)
            ->get(route('x-167.stock-by-van'))
            ->assertOk()
            ->assertSee('372.64');
    }


    public function test_an_item_with_no_van_lands_under_no_van(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Livewire::actingAs($user)->test(StockByVan::class)
            ->set('newName', 'Bay 3 Sealant')
            ->set('newSku', 'SEAL-01')
            ->call('addItem')
            ->assertSee('Bay 3 Sealant')
            ->assertSee('No van');

        $item = StockItem::where('business_id', $biz->id)->where('sku', 'SEAL-01')->first();
        $this->assertNotNull($item);
        $this->assertNull($item->location_id);
    }

    public function test_adding_an_item_without_a_name_is_refused_and_writes_nothing(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Livewire::actingAs($user)->test(StockByVan::class)
            ->set('newName', '')
            ->set('newSku', 'NOPE-1')
            ->call('addItem')
            ->assertSee('An item needs a name and a SKU.');

        $this->assertSame(0, StockItem::where('business_id', $biz->id)->count());
    }
}
