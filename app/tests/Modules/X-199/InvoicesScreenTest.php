<?php

declare(strict_types=1);

namespace Tests\Modules\X199;

use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Ui\Invoices;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class InvoicesScreenTest extends TestCase
{
    public function test_invoices_screen(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        $otherBiz = self::provisionTenant();
        Tenancy::set($otherBiz->id);
        $otherCustomer = Person::create([
            'business_id' => $otherBiz->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);
        $invOther = app(InvoiceEngine::class)->issueInvoice($otherBiz->id, $otherCustomer->id, [['description' => 'Other tenant thing', 'quantity' => 1, 'unit_price_cents' => 77700]])['invoice'];

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $engine = app(InvoiceEngine::class);

        $res1 = $engine->issueInvoice($biz->id, $customer->id, [
            ['description' => 'Item 1', 'quantity' => 1, 'unit_price_cents' => 10000],
        ]);
        $inv1 = $res1['invoice'];

        // Ensure they have different created_at
        $inv1->update(['created_at' => now()->subHour()]);

        $engine->recordPayment($biz->id, $inv1->id, 10000);

        $res2 = $engine->issueInvoice($biz->id, $customer->id, [
            ['description' => 'Item 2', 'quantity' => 2, 'unit_price_cents' => 5000],
        ]);
        $inv2 = $res2['invoice'];

        Tenancy::forgetUser();
        Livewire::test(Invoices::class)
            ->assertForbidden();

        Tenancy::setUser($owner->id);
        Livewire::actingAs($owner)->test(Invoices::class)
            ->assertOk()
            ->assertSeeInOrder([$inv2->invoice_number, $inv1->invoice_number])
            ->assertSee('John Doe')
            ->assertDontSee('Jane Doe')
            ->assertDontSee('777.00')
            ->assertSee('Receipt not available')
            ->assertDontSee('cdn.goaiez.com')
            ->call('toggleExpanded', $inv1->id)
            ->assertSee('Item 1')
            ->call('recordPayment', $inv2->id)
            ->call('recordPayment', 999999)
            ->assertSee("isn't in this account");

        $this->assertEquals('paid', Invoice::find($inv2->id)->status);
    }
}
