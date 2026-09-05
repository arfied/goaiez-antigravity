<?php

declare(strict_types=1);

namespace Tests\Modules\X199;

use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X199\Actions\TermsSetAction;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Ui\Unpaid;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class UnpaidScreenTest extends TestCase
{
    public function test_unpaid_screen(): void
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
            'first_name' => 'Alice',
            'last_name' => 'Smith',
        ]);

        $engine = app(InvoiceEngine::class);
        $termsSet = app(TermsSetAction::class);

        // Paid invoice
        $res1 = $engine->issueInvoice($biz->id, $customer->id, [
            ['description' => 'Item Paid', 'quantity' => 1, 'unit_price_cents' => 10000],
        ]);
        $inv1 = $res1['invoice'];
        $engine->recordPayment($biz->id, $inv1->id, 10000);

        // Unpaid invoice
        $res2 = $engine->issueInvoice($biz->id, $customer->id, [
            ['description' => 'Item Unpaid', 'quantity' => 1, 'unit_price_cents' => 25000],
        ]);
        $inv2 = $res2['invoice'];

        // Over-limit invoice
        $customer2 = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'Bob',
            'last_name' => 'Jones',
        ]);

        // Set tight terms
        $termsSet->handle($biz->id, $customer2->id, 'net_30', 5000, 'tok_123');

        // Issue 150.00 invoice which is > 50.00 limit -> overflow 100.00
        $res3 = $engine->issueInvoice($biz->id, $customer2->id, [
            ['description' => 'Item Expensive', 'quantity' => 1, 'unit_price_cents' => 15000],
        ]);
        $inv3 = $res3['invoice'];

        Tenancy::forgetUser();
        Livewire::test(Unpaid::class)
            ->assertForbidden();

        Tenancy::setUser($owner->id);
        Livewire::actingAs($owner)->test(Unpaid::class)
            ->assertOk()
            ->assertSee($inv2->invoice_number)
            ->assertSee($inv3->invoice_number)
            ->assertDontSee($inv1->invoice_number)
            ->assertSee('250.00') // Outstanding for inv2
            ->assertSee('covered by the card on file; service never stopped')
            ->assertDontSee($invOther->invoice_number)
            ->assertDontSee('777.00')
            ->assertSee('Not overdue')
            ->call('recordPayment', 999999)
            ->assertSee("isn't in this account")
            ->call('toggleExpanded', $inv2->id)
            ->assertSee('Total:')
            ->call('showPaid')
            ->assertSee($inv1->invoice_number)
            ->assertDontSee($inv2->invoice_number);
    }
}
