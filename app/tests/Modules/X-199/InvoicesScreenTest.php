<?php

declare(strict_types=1);

namespace Tests\Modules\X199;

use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X199\Actions\InvoiceDraftAction;
use App\Modules\X199\Domain\InvoiceEngine;
use App\Modules\X199\Models\CreditTerm;
use App\Modules\X199\Models\Invoice;
use App\Modules\X199\Ui\Invoices;
use App\Support\Tenancy;
use Carbon\Carbon;
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

    public function test_invoices_screen_says_nothing_raises_or_sends_an_invoice_when_the_list_is_empty(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(Invoices::class)
            ->assertSee('Nothing in this checkout raises one from a completed job')
            ->assertDontSee('R235');
    }

    public function test_a_draft_row_says_it_cannot_be_issued_rather_than_reporting_a_missing_pdf(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        app(InvoiceDraftAction::class)->handle($biz->id, $customer->id, [
            ['description' => 'Draft Item', 'quantity' => 1, 'unit_price_cents' => 10000],
        ]);

        Livewire::actingAs($owner)->test(Invoices::class)
            ->assertSee('nothing here issues a draft yet')
            ->assertDontSee('PDF not available');
    }

    public function test_the_invoice_list_is_ordered_when_every_row_shares_one_timestamp(): void
    {
        $base = now()->startOfWeek()->addDays(3)->setTime(10, 0);
        Carbon::setTestNow($base);

        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'John', 'last_name' => 'Doe']);

        Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-ORD-A',
            'total_cents' => 90000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(10),
        ]);

        Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-ORD-B',
            'total_cents' => 90000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(10),
        ]);

        Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-ORD-C',
            'total_cents' => 90000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(10),
        ]);

        Livewire::actingAs($owner)->test(Invoices::class)->assertSeeInOrder(['INV-ORD-C', 'INV-ORD-B', 'INV-ORD-A']);

        Carbon::setTestNow();
    }

    public function test_can_draft_an_invoice(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $component = Livewire::actingAs($owner)->test(Invoices::class)
            ->set('customerId', (string) $customer->id)
            ->set('lineDescription', 'Culvert jetting and root cut')
            ->set('lineQuantity', 2)
            ->set('lineUnitPriceCents', 173609)
            ->call('draftInvoice')
            ->assertSet('error', null);

        $success = $component->get('success');
        $this->assertStringContainsString('Drafted invoice INV', (string) $success);
        $this->assertStringContainsString(',472.18', (string) $success);
        $this->assertStringContainsString('the 30-day default, because no credit terms are set for them', (string) $success);
        $this->assertStringContainsString('nothing issues it, nothing sends it', (string) $success);

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'total_cents' => 347218,
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('invoice_lines', [
            'description' => 'Culvert jetting and root cut',
            'quantity' => 2,
            'unit_price_cents' => 173609,
            'subtotal_cents' => 347218,
        ]);
    }

    public function test_the_invoice_appears_in_the_list(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->actingAs($owner)->get(route('x-199.invoices'))->assertOk()->assertSee('No invoice has been raised');

        Livewire::actingAs($owner)->test(Invoices::class)
            ->set('customerId', (string) $customer->id)
            ->set('lineDescription', 'Culvert jetting and root cut')
            ->set('lineQuantity', 2)
            ->set('lineUnitPriceCents', 173609)
            ->call('draftInvoice');

        $invoice = Invoice::where('business_id', $biz->id)->firstOrFail();

        $this->actingAs($owner)->get(route('x-199.invoices'))->assertOk()
            ->assertDontSee('No invoice has been raised')
            ->assertSee($invoice->invoice_number)
            ->assertSee('3,472.18');
    }

    public function test_credit_terms_are_applied_and_named(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        CreditTerm::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'terms_type' => 'net_15',
        ]);

        $component = Livewire::actingAs($owner)->test(Invoices::class)
            ->set('customerId', (string) $customer->id)
            ->set('lineDescription', 'Culvert jetting and root cut')
            ->set('lineQuantity', 2)
            ->set('lineUnitPriceCents', 173609)
            ->call('draftInvoice');

        $success = $component->get('success');
        $this->assertStringContainsString('net_15', (string) $success);

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customer->id,
            'due_date' => now()->addDays(15)->toDateString(),
        ]);
    }

    public function test_drafting_without_a_customer_is_refused(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(Invoices::class)
            ->set('customerId', '')
            ->set('lineDescription', 'Culvert jetting and root cut')
            ->set('lineQuantity', 2)
            ->set('lineUnitPriceCents', 173609)
            ->call('draftInvoice')
            ->assertSet('error', 'Choose a customer before drafting an invoice.');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_drafting_with_another_tenants_customer_is_refused(): void
    {
        $bizB = self::provisionTenant();
        $ownerB = User::findOrFail($bizB->owner_user_id);
        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);
        $this->actingAs($ownerB);
        $customerB = Person::create([
            'business_id' => $bizB->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);

        $bizA = self::provisionTenant();
        $ownerA = User::findOrFail($bizA->owner_user_id);
        Tenancy::setUser($ownerA->id);
        Tenancy::set((int) $bizA->id);
        $this->actingAs($ownerA);

        Livewire::actingAs($ownerA)->test(Invoices::class)
            ->set('customerId', (string) $customerB->id)
            ->set('lineDescription', 'Culvert jetting and root cut')
            ->set('lineQuantity', 2)
            ->set('lineUnitPriceCents', 173609)
            ->call('draftInvoice')
            ->assertSet('error', 'Choose a customer before drafting an invoice.');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_the_empty_state_no_longer_claims_nothing_can_raise_an_invoice(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $this->actingAs($owner)->get(route('x-199.invoices'))->assertOk()
            ->assertSee('No invoice has been raised for this account. Nothing in this checkout raises one from a completed job, and nothing sends an invoice once it exists, but an owner can raise one here by hand.');
    }
}
