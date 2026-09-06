<?php

declare(strict_types=1);

namespace Tests\Modules\X199;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Ui\Invoices;
use App\Support\Tenancy;
use Database\Factories\PersonFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class InvoicesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_invoices_data_and_tenant_isolation(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Invoices Tenant 1']);
        Tenancy::set((int) $biz->id);
        $customer = PersonFactory::new()->create(['business_id' => $biz->id]);

        Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-INV-001',
            'total_cents' => 35000,
            'paid_cents' => 5000,
            'status' => 'due',
            'due_date' => now()->addDays(5)->toDateString(),
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        $otherBiz = TestCase::provisionTenant(['name' => 'Invoices Tenant 2']);
        Tenancy::actingAs($otherBiz->id, function () use ($otherBiz) {
            $otherCustomer = PersonFactory::new()->create(['business_id' => $otherBiz->id]);
            Invoice::create([
                'business_id' => $otherBiz->id,
                'customer_id' => $otherCustomer->id,
                'invoice_number' => 'INV-INV-002-ISOLATED',
                'total_cents' => 99900,
                'paid_cents' => 0,
                'status' => 'due',
                'due_date' => now()->toDateString(),
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        });

        Tenancy::set((int) $biz->id);

        Livewire::test(Invoices::class, ['businessId' => $biz->id])
            ->assertSee('1 invoices')
            ->assertSee('$350.00')
            ->assertSee('INV-INV-001')
            ->assertDontSee('INV-INV-002-ISOLATED')
            ->assertDontSee('$999.00');

        Invoice::where('business_id', $biz->id)->delete();
        Livewire::test(Invoices::class, ['businessId' => $biz->id])
            ->assertSee('0 invoices')
            ->assertSee('$0.00')
            ->assertSee('No customer invoices have been generated yet');
    }

    public function test_route_renders_invoices(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-199.invoices'))->assertOk();
    }
}
