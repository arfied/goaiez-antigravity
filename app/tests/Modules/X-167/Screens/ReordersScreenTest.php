<?php

declare(strict_types=1);

namespace Tests\Modules\X167\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X167\Actions\ReorderProposeAction;
use App\Modules\X167\Models\PurchaseOrder;
use App\Modules\X167\Models\Supplier;
use App\Modules\X167\Ui\Reorders;
use App\Modules\X202\Models\ApprovalItem;
use App\Modules\X202\Ui\Queue;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

        $supplier = Supplier::create([
            'business_id' => $biz->id,
            'name' => 'Distinctive Supplier 881',
        ]);

        PurchaseOrder::create([
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

    public function test_requesting_approval_enqueues_an_item(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $po = app(ReorderProposeAction::class)->handle(
            (int) $biz->id,
            null,
            [['stock_item_id' => 1, 'sku' => 'A', 'name' => 'B', 'qty' => 5, 'unit' => 'pcs']],
            0
        );

        Livewire::test(Reorders::class)
            ->call('requestApproval', $po->id)
            ->assertSet('error', null)
            ->assertSee('Approval requested for '.$po->po_number);

        $this->assertDatabaseHas('approval_items', [
            'item_type' => 'purchase_order_send',
            'subject' => 'Send purchase order '.$po->po_number,
            'status' => 'pending',
        ]);
    }

    public function test_sending_without_an_approval_is_refused(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $po = app(ReorderProposeAction::class)->handle(
            (int) $biz->id,
            null,
            [['stock_item_id' => 1, 'sku' => 'A', 'name' => 'B', 'qty' => 5, 'unit' => 'pcs']],
            0
        );

        Livewire::test(Reorders::class)
            ->call('sendPo', $po->id)
            ->assertSee('No approved approval exists for '.$po->po_number);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'proposed',
        ]);
    }

    public function test_sending_with_a_pendin_g_approval_is_still_refused(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $po = app(ReorderProposeAction::class)->handle(
            (int) $biz->id,
            null,
            [['stock_item_id' => 1, 'sku' => 'A', 'name' => 'B', 'qty' => 5, 'unit' => 'pcs']],
            0
        );

        Livewire::test(Reorders::class)
            ->call('requestApproval', $po->id);

        Livewire::test(Reorders::class)
            ->call('sendPo', $po->id)
            ->assertSee('No approved approval exists for '.$po->po_number);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'proposed',
        ]);
    }

    public function test_an_approved_request_lets_the_order_be_sent(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $po = app(ReorderProposeAction::class)->handle(
            (int) $biz->id,
            null,
            [['stock_item_id' => 1, 'sku' => 'A', 'name' => 'B', 'qty' => 5, 'unit' => 'pcs']],
            0
        );

        Livewire::test(Reorders::class)
            ->call('requestApproval', $po->id);

        $item = ApprovalItem::where('subject', 'Send purchase order '.$po->po_number)->firstOrFail();

        Livewire::test(Queue::class)
            ->call('approveItem', $item->id);

        Livewire::test(Reorders::class)
            ->call('sendPo', $po->id)
            ->assertSet('error', null)
            ->assertSee('is marked sent, against approval #')
            ->assertSee('Nothing emails the supplier');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'sent',
            'approved_action_id' => (string) $item->id,
        ]);
    }

    public function test_the_list_shows_the_order_as_sent(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $po = app(ReorderProposeAction::class)->handle(
            (int) $biz->id,
            null,
            [['stock_item_id' => 1, 'sku' => 'A', 'name' => 'B', 'qty' => 5, 'unit' => 'pcs']],
            0
        );

        Tenancy::forget();
        $this->get(route('x-167.reorders'))
            ->assertOk()
            ->assertSee('Proposed');

        Tenancy::set((int) $biz->id);

        Livewire::test(Reorders::class)
            ->call('requestApproval', $po->id);

        $item = ApprovalItem::where('subject', 'Send purchase order '.$po->po_number)->firstOrFail();

        Livewire::test(Queue::class)
            ->call('approveItem', $item->id);

        Livewire::test(Reorders::class)
            ->call('sendPo', $po->id);

        Tenancy::forget();

        $this->get(route('x-167.reorders'))
            ->assertOk()
            ->assertSee('Sent');
    }

    public function test_requesting_approval_for_another_tenants_order_is_refused(): void
    {
        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);
        Tenancy::set((int) $bizB->id);
        $poB = app(ReorderProposeAction::class)->handle(
            (int) $bizB->id,
            null,
            [['stock_item_id' => 1, 'sku' => 'A', 'name' => 'B', 'qty' => 5, 'unit' => 'pcs']],
            0
        );

        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);
        $this->actingAs($ownerA);
        Tenancy::set((int) $bizA->id);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(Reorders::class)
            ->call('requestApproval', $poB->id);
    }
}
