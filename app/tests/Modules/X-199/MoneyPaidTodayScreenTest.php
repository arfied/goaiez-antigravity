<?php

declare(strict_types=1);

namespace Tests\Modules\X199;

use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Ui\MoneyPaidToday;
use App\Services\Config\DefaultsRegistry;
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

        // tenant B first
        Tenancy::set($otherBiz->id);
        $customerB = Person::create(['business_id' => $otherBiz->id, 'first_name' => 'B', 'last_name' => 'B']);
        $invB = Invoice::create([
            'business_id' => $otherBiz->id,
            'customer_id' => $customerB->id,
            'invoice_number' => 'INV-OTHER',
            'total_cents' => 100,
            'paid_cents' => 100,
            'status' => 'paid',
            'due_date' => now(),
            'paid_at' => now(),
        ]);

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);
        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Alice', 'last_name' => 'Smith']);

        // an old invoice paid yesterday and edited today (paid_at yesterday, updated_at now) must NOT appear <- the mutation line
        $old = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-OLD',
            'total_cents' => 500,
            'paid_cents' => 500,
            'status' => 'paid',
            'due_date' => now()->subDay(),
            'paid_at' => now()->subDay(),
            'updated_at' => now(), // edited today
        ]);

        // today's invoice paid through recordPayment() appears with its Paid: time and its lines on explain
        $engine = new InvoiceEngine(app(DefaultsRegistry::class));
        $issued = $engine->issueInvoice($biz->id, $customer->id, [
            ['description' => 'Roofing service', 'quantity' => 1, 'unit_price_cents' => 10000],
            ['description' => 'Materials', 'quantity' => 1, 'unit_price_cents' => 2500],
        ]);
        $invA = $issued['invoice'];

        $engine->recordPayment($biz->id, $invA->id);
        $invA->refresh();

        $unpaid = $engine->issueInvoice($biz->id, $customer->id, [
            ['description' => 'Unpaid thing', 'quantity' => 1, 'unit_price_cents' => 5000],
        ])['invoice'];

        Tenancy::forgetUser();
        Livewire::test(MoneyPaidToday::class)->assertForbidden();

        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(MoneyPaidToday::class)
            ->assertOk()
            ->assertSee($invA->invoice_number)
            ->assertSee('125.00')
            ->assertSee('Paid: '.$invA->paid_at->format('g:i A'))
            ->assertDontSee('INV-OLD')
            ->assertDontSee($unpaid->invoice_number)
            ->assertDontSee('INV-OTHER')
            ->call('explain', $invA->id)
            ->assertSee('Roofing service')
            ->assertSee('Materials')
            ->call('explain', $old->id)
            ->assertSee("isn't in this account")
            ->call('explain', 999999)
            ->assertSee("isn't in this account");

        $this->assertNotNull(Invoice::find($invA->id)->paid_at);
    }
}
