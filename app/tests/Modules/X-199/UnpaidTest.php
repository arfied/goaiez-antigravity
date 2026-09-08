<?php

declare(strict_types=1);

namespace Tests\Modules\X199;

use App\Models\User;
use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Ui\Unpaid;
use App\Support\Tenancy;
use Database\Factories\PersonFactory;
use Database\Seeders\UiReviewSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class UnpaidTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unpaid_data_and_tenant_isolation(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Unpaid Tenant 1']);
        Tenancy::set((int) $biz->id);
        $customer = PersonFactory::new()->create(['business_id' => $biz->id]);

        Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-UNP-001',
            'total_cents' => 35000,
            'paid_cents' => 5000,
            'status' => 'due',
            'due_date' => now()->addDays(5)->toDateString(),
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        $otherBiz = TestCase::provisionTenant(['name' => 'Unpaid Tenant 2']);
        Tenancy::actingAs($otherBiz->id, function () use ($otherBiz) {
            $otherCustomer = PersonFactory::new()->create(['business_id' => $otherBiz->id]);
            Invoice::create([
                'business_id' => $otherBiz->id,
                'customer_id' => $otherCustomer->id,
                'invoice_number' => 'INV-UNP-002-ISOLATED',
                'total_cents' => 99900,
                'paid_cents' => 0,
                'status' => 'due',
                'due_date' => now()->toDateString(),
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        });

        Tenancy::set((int) $biz->id);

        // 1. Data assertion + 3. Tenant isolation
        Livewire::test(Unpaid::class, ['businessId' => $biz->id])
            ->assertSee('1 unpaid')
            ->assertSee('$300.00')
            ->assertSee('INV-UNP-001')
            ->assertDontSee('INV-UNP-002-ISOLATED')
            ->assertDontSee('$999.00')
            ->assertDontSee('tok_placeholder');

        // 2. Empty state
        Invoice::where('business_id', $biz->id)->delete();
        Livewire::test(Unpaid::class, ['businessId' => $biz->id])
            ->assertSee('0 unpaid')
            ->assertSee('$0.00')
            ->assertSee('All issued invoices have been paid');
    }

    public function test_home_renders_unpaid(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Home Unpaid Tenant']);
        Tenancy::set((int) $biz->id);
        $this->seed(UiReviewSeeder::class);
        $owner = User::where('email', 'owner2@business.com')->first();

        $this->actingAs($owner)->get('/home')
            ->assertOk();
    }

    public function test_home_renders_money_quick_actions(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Home Actions Tenant']);
        Tenancy::set((int) $biz->id);
        $this->seed(UiReviewSeeder::class);
        $owner = User::where('email', 'owner2@business.com')->first();

        $this->actingAs($owner)->get('/home')
            ->assertOk()
            ->assertSee(route('x-199.invoices'))
            ->assertSee(route('x-199.credits'));
    }
}
