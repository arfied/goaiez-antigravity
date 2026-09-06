<?php

declare(strict_types=1);

namespace Tests\Modules\X199;

use App\Models\User;
use App\Modules\X199\Models\OverflowCharge;
use App\Modules\X199\Ui\Declines;
use App\Support\Tenancy;
use Database\Factories\PersonFactory;
use Database\Seeders\UiReviewSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class DeclinesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_declines_data_and_tenant_isolation(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Declines Tenant 1']);
        Tenancy::set((int) $biz->id);
        $customer = PersonFactory::new()->create(['business_id' => $biz->id]);

        OverflowCharge::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_id' => 1,
            'charge_type' => 'overflow_reversed',
            'amount_cents' => 88000,
            'card_token' => 'tok_placeholder',
            'reference_id' => 'REF-DEC-001',
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        $otherBiz = TestCase::provisionTenant(['name' => 'Declines Tenant 2']);
        Tenancy::actingAs($otherBiz->id, function () use ($otherBiz) {
            $otherCustomer = PersonFactory::new()->create(['business_id' => $otherBiz->id]);
            OverflowCharge::create([
                'business_id' => $otherBiz->id,
                'customer_id' => $otherCustomer->id,
                'invoice_id' => 2,
                'charge_type' => 'overflow_reversed',
                'amount_cents' => 11000,
                'card_token' => 'tok_placeholder2',
                'reference_id' => 'REF-DEC-002-ISOLATED',
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        });

        // 1. Data assertion + 3. Tenant isolation
        Livewire::test(Declines::class, ['businessId' => $biz->id])
            ->assertSee('1 declined')
            ->assertSee('$880.00')
            ->assertSee('REF-DEC-001')
            ->assertDontSee('REF-DEC-002-ISOLATED')
            ->assertDontSee('$110.00')
            ->assertDontSee('tok_placeholder');

        // 2. Empty state
        OverflowCharge::where('business_id', $biz->id)->delete();
        Livewire::test(Declines::class, ['businessId' => $biz->id])
            ->assertSee('0 declined')
            ->assertSee('$0.00')
            ->assertSee('No payments have been declined or reversed');
    }

    public function test_home_renders_declines(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Home Declines Tenant']);
        Tenancy::set((int) $biz->id);
        $this->seed(UiReviewSeeder::class);
        $owner = User::where('email', 'owner2@business.com')->first();

        $this->actingAs($owner)->get('/home')
            ->assertOk();
    }
}
