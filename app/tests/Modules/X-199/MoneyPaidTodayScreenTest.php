<?php

declare(strict_types=1);

namespace Tests\Modules\X199;

use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Models\InvoiceLine;
use App\Modules\X199\Ui\MoneyPaidToday;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class MoneyPaidTodayScreenTest extends TestCase
{
    public function test_money_paid_today_screen(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        $otherBiz = self::provisionTenant();

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'Alice',
            'last_name' => 'Smith',
        ]);

        $invA = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-001',
            'total_cents' => 12500,
            'paid_cents' => 12500,
            'status' => 'paid',
            'due_date' => now()->subDay(),
            'created_at' => now()->subDays(2),
            'updated_at' => now(), // explicitly stamp today
        ]);

        InvoiceLine::create([
            'business_id' => $biz->id,
            'invoice_id' => $invA->id,
            'description' => 'Roofing Service',
            'quantity' => 1,
            'unit_price_cents' => 10000,
            'subtotal_cents' => 10000,
        ]);
        
        InvoiceLine::create([
            'business_id' => $biz->id,
            'invoice_id' => $invA->id,
            'description' => 'Materials',
            'quantity' => 1,
            'unit_price_cents' => 2500,
            'subtotal_cents' => 2500,
        ]);

        $unpaidA = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-002',
            'total_cents' => 5000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDay(),
            'created_at' => now()->subDays(2),
            'updated_at' => now(),
        ]);

        Tenancy::set($otherBiz->id);
        $customerB = Person::create([
            'business_id' => $otherBiz->id,
            'first_name' => 'Bob',
            'last_name' => 'Jones',
        ]);

        $invB = Invoice::create([
            'business_id' => $otherBiz->id,
            'customer_id' => $customerB->id,
            'invoice_number' => 'INV-OTHER',
            'total_cents' => 45000,
            'paid_cents' => 45000,
            'status' => 'paid',
            'due_date' => now()->subDay(),
            'created_at' => now()->subDays(2),
            'updated_at' => now(),
        ]);

        Tenancy::set($biz->id);
        Tenancy::forgetUser();
        Livewire::test(MoneyPaidToday::class)
            ->assertForbidden();

        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(MoneyPaidToday::class)
            ->assertOk()
            ->assertSee('INV-001')
            ->assertSee('125.00')
            ->assertDontSee('INV-OTHER')
            ->assertDontSee('450.00')
            ->assertDontSee('INV-002')
            ->call('explain', $invA->id)
            ->assertSee('Roofing Service')
            ->assertSee('Materials')
            ->call('explain', 999999)
            ->assertSee("isn't in this account");
    }
}
