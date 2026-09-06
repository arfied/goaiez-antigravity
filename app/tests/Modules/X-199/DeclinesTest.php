<?php

declare(strict_types=1);

namespace Tests\Modules\X199;

use App\Models\User;
use App\Modules\X199\Models\Invoice;
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
        $base = \Carbon\Carbon::now()->startOfWeek()->copy()->addDays(6);
        \Carbon\Carbon::setTestNow($base);

        $biz = TestCase::provisionTenant(['name' => 'Declines Tenant 1']);
        Tenancy::set((int) $biz->id);
        $customer = PersonFactory::new()->create(['business_id' => $biz->id]);

        $invoice1 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-DEC-001',
            'total_cents' => 88000,
            'paid_cents' => 0,
            'status' => 'due',
            'due_date' => $base->copy()->toDateString(),
            'updated_at' => $base,
            'created_at' => $base,
        ]);

        OverflowCharge::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice1->id,
            'charge_type' => 'overflow_reversed',
            'amount_cents' => 88000,
            'card_token' => 'tok_placeholder',
            'reference_id' => 'REF-DEC-001',
            'updated_at' => $base,
            'created_at' => $base,
        ]);

        \App\Modules\X198\Models\Payment::create([
            'business_id' => $biz->id,
            'amount_cents' => 88000,
            'currency' => 'USD',
            'payment_token' => 'tok_placeholder',
            'idempotency_key' => 'idemp1',
            'status' => 'failed',
            'created_at' => \Carbon\Carbon::now()->startOfWeek(),
        ]);

        $otherBiz = TestCase::provisionTenant(['name' => 'Declines Tenant 2']);
        Tenancy::actingAs($otherBiz->id, function () use ($otherBiz, $base) {
            $otherCustomer = PersonFactory::new()->create(['business_id' => $otherBiz->id]);
            $invoice2 = Invoice::create([
                'business_id' => $otherBiz->id,
                'customer_id' => $otherCustomer->id,
                'invoice_number' => 'INV-DEC-002-ISOLATED',
                'total_cents' => 11000,
                'paid_cents' => 0,
                'status' => 'due',
                'due_date' => $base->copy()->toDateString(),
                'updated_at' => $base,
                'created_at' => $base,
            ]);

            OverflowCharge::create([
                'business_id' => $otherBiz->id,
                'customer_id' => $otherCustomer->id,
                'invoice_id' => $invoice2->id,
                'charge_type' => 'overflow_reversed',
                'amount_cents' => 11000,
                'card_token' => 'tok_placeholder2',
                'reference_id' => 'REF-DEC-002-ISOLATED',
                'updated_at' => $base,
                'created_at' => $base,
            ]);

            \App\Modules\X198\Models\Payment::create([
                'business_id' => $otherBiz->id,
                'amount_cents' => 11000,
                'currency' => 'USD',
                'payment_token' => 'tok_placeholder2',
                'idempotency_key' => 'idemp2',
                'status' => 'failed',
                'created_at' => \Carbon\Carbon::now()->startOfWeek(),
            ]);
        });

        Tenancy::set((int) $biz->id);

        $owner = User::findOrFail($biz->owner_user_id);
        // 1. Data assertion + 3. Tenant isolation
        Livewire::actingAs($owner)->test(Declines::class, ['businessId' => $biz->id])
            ->assertSee('1')
            ->assertSee('880.00')
            ->assertDontSee('110.00');

        // 2. Empty state
        \App\Modules\X198\Models\Payment::where('business_id', $biz->id)->delete();
        Livewire::actingAs($owner)->test(Declines::class, ['businessId' => $biz->id])
            ->assertSee('0')
            ->assertSee('You have no declined payments to review.');

        \Carbon\Carbon::setTestNow();
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
