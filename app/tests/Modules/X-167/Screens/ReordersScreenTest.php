<?php

declare(strict_types=1);

namespace Tests\Modules\X167\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X167\Ui\Reorders;
use Livewire\Livewire;
use Tests\TestCase;

class ReordersScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-167.reorders'))
            ->assertOk()
            ->assertSee('<h2>Reorders</h2>', false)
            ->assertSee('No reorders yet.');

        $supplier = \App\Modules\X167\Models\Supplier::create([
            'business_id' => $biz->id,
            'name' => 'Distinctive Supplier 881',
        ]);

        \App\Modules\X167\Models\PurchaseOrder::create([
            'business_id' => $biz->id,
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-9912',
            'status' => 'proposed',
            'total_cents' => 5000,
            'items' => [['sku' => 'SKU-1', 'qty' => 5, 'unit_price_cents' => 1000]],
            'is_sample' => false,
        ]);

        $this->get(route('x-167.reorders'))
            ->assertOk()
            ->assertSee('Distinctive Supplier 881')
            ->assertSee('PO-9912');

        Livewire::test(Reorders::class)->assertOk();
    }
}
