<?php

declare(strict_types=1);

namespace Tests\Modules\X199;

use App\Models\User;
use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Ui\MoneyPaidToday;
use App\Support\Tenancy;
use Carbon\Carbon;
use Database\Factories\PersonFactory;
use Database\Seeders\UiReviewSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class MoneyPaidTodayTest extends TestCase
{
    use DatabaseTransactions;

    public function test_money_paid_today(): void
    {
        $base = Carbon::now()->startOfWeek()->copy()->addDays(6);
        Carbon::setTestNow($base);

        $biz = TestCase::provisionTenant(['name' => 'Money Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $customer = PersonFactory::new()->create(['business_id' => $biz->id]);

        Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-TEST-001',
            'total_cents' => 12500,
            'paid_cents' => 12500,
            'status' => 'paid',
            'paid_at' => $base,
            'due_date' => $base->copy()->toDateString(),
            'updated_at' => $base,
            'created_at' => $base,
        ]);

        $owner = User::findOrFail($biz->owner_user_id);
        Livewire::actingAs($owner)->test(MoneyPaidToday::class, ['businessId' => $biz->id])
            ->assertViewHas('totalCents', 12500)
            ->assertSee('125.00')
            ->assertSee('INV-TEST-001');

        Invoice::where('business_id', $biz->id)->delete();

        // empty state
        Livewire::actingAs($owner)->test(MoneyPaidToday::class, ['businessId' => $biz->id])
            ->assertViewHas('totalCents', 0)
            ->assertSee('0.00')
            ->assertSee('No paid invoices today.');

        Carbon::setTestNow();
    }

    public function test_home_renders_money_paid(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Home Tenant']);
        Tenancy::set((int) $biz->id);
        $this->seed(UiReviewSeeder::class);
        $owner = User::where('email', 'owner2@business.com')->first();

        $this->actingAs($owner)->get('/home')
            ->assertOk();
    }
}
