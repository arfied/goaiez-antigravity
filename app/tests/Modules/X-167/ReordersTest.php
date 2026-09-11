<?php

declare(strict_types=1);

namespace Tests\Modules\X167;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X167\Actions\ReorderProposeAction;
use App\Modules\X167\Models\Supplier;
use App\Modules\X167\Ui\Reorders;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ReordersTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(Reorders::class)->assertForbidden();
    }

    public function test_staff_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Staff;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(Reorders::class)->assertForbidden();
    }

    public function test_empty_sentence(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(Reorders::class)
            ->assertSee('No reorders yet. Stock at its reorder point is flagged on Stock by van; propose a restock there and it appears here.');
    }

    public function test_seeded_reorders_show_correctly(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $supplier = Supplier::create([
            'business_id' => $biz->id,
            'name' => 'HVAC Wholesale Supply',
            'email' => 'orders@hvacwholesale.test',
        ]);

        $po = app(ReorderProposeAction::class)->handle(
            businessId: $biz->id,
            supplierId: $supplier->id,
            items: [['sku' => 'COPPER-10M-SPOOL', 'qty' => 5, 'unit' => 'm', 'unit_price_cents' => 4500]],
            totalCents: 22500
        );

        Livewire::actingAs($user)->test(Reorders::class)
            ->assertSee($po->po_number)
            ->assertSee('HVAC Wholesale Supply')
            ->assertSee('225.00')
            ->assertSee('Proposed')
            ->assertDontSee('<span>Sample</span>', false);
    }

    public function test_toggle_shows_items(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $supplier = Supplier::create([
            'business_id' => $biz->id,
            'name' => 'HVAC Wholesale Supply',
            'email' => 'orders@hvacwholesale.test',
        ]);

        $po = app(ReorderProposeAction::class)->handle(
            businessId: $biz->id,
            supplierId: $supplier->id,
            items: [['sku' => 'COPPER-10M-SPOOL', 'qty' => 5, 'unit' => 'm', 'unit_price_cents' => 4500]],
            totalCents: 22500
        );

        Livewire::actingAs($user)->test(Reorders::class)
            ->assertDontSee('COPPER-10M-SPOOL')
            ->call('toggle', $po->id)
            ->assertSee('COPPER-10M-SPOOL');
    }

    public function test_sample_pill_shown(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $supplier = Supplier::create([
            'business_id' => $biz->id,
            'name' => 'HVAC Wholesale Supply',
            'email' => 'orders@hvacwholesale.test',
        ]);

        $po = app(ReorderProposeAction::class)->handle(
            businessId: $biz->id,
            supplierId: $supplier->id,
            items: [['sku' => 'COPPER-10M-SPOOL', 'qty' => 5, 'unit' => 'm', 'unit_price_cents' => 4500]],
            totalCents: 22500
        );

        $po->is_sample = true;
        $po->save();

        Livewire::actingAs($user)->test(Reorders::class)
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

        $supplier = Supplier::create([
            'business_id' => $biz->id,
            'name' => 'HVAC Wholesale Supply',
            'email' => 'orders@hvacwholesale.test',
        ]);

        app(ReorderProposeAction::class)->handle(
            businessId: $biz->id,
            supplierId: $supplier->id,
            items: [['sku' => 'COPPER-10M-SPOOL', 'qty' => 5, 'unit' => 'm', 'unit_price_cents' => 10239]],
            totalCents: 51199
        );

        $this->actingAs($user)
            ->get(route('x-167.reorders'))
            ->assertOk()
            ->assertSee('511.99');
    }
}
