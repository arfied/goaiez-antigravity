<?php

declare(strict_types=1);

namespace Tests\Modules\X199;

use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Ui\MoneyPaidToday;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class MoneyPaidTodayTest extends TestCase
{
    use DatabaseTransactions;

    public function test_money_paid_today(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Money Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => 1,
            'invoice_number' => 'INV-TEST-001',
            'total_cents' => 12500,
            'paid_cents' => 12500,
            'status' => 'paid',
            'due_date' => now()->toDateString(),
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        Livewire::test(MoneyPaidToday::class, ['businessId' => $biz->id])
            ->assertSee('$125.00')
            ->assertSee('INV-TEST-001');
            

        Invoice::where('business_id', $biz->id)->delete();

        // empty state
        Livewire::test(MoneyPaidToday::class, ['businessId' => $biz->id])
            ->assertSee('$0.00')
            ->assertSee('No invoices have been paid today');
    }

    public function test_home_renders_money_paid(): void
    {
        $biz = \Tests\TestCase::provisionTenant(['name' => 'Home Tenant']);
        \App\Support\Tenancy::set((int) $biz->id);
        $this->seed(\Database\Seeders\UiReviewSeeder::class);
        $owner = \App\Models\User::where('email', 'owner2@business.com')->first();

        $this->actingAs($owner)->get('/home')
            ->assertOk()
            ->assertSee('14 missed calls');
    }
}
