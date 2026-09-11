<?php

declare(strict_types=1);

namespace Tests\Modules\X211;

use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X199\Models\Invoice;
use App\Modules\X211\Events\ArFeeApplied;
use App\Modules\X211\Events\ArLateFeeTermSet;
use App\Modules\X211\Events\ArOverdue;
use App\Modules\X211\Listeners\ProcessOverdueReceivable;
use App\Modules\X211\Models\ArDunningAction;
use App\Modules\X211\Models\ArPlanTerm;
use App\Modules\X211\Models\OfflinePayment;
use App\Modules\X211\Models\ReceivableState;
use App\Modules\X211\Ui\AgeingByReason;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class AgeingByReasonScreenTest extends TestCase
{
    public function test_ageing_by_reason_groups_and_logs_payment(): void
    {
        $biz = self::provisionTenant();
        $bizB = self::provisionTenant();

        Tenancy::set($bizB->id);
        Invoice::create([
            'business_id' => $bizB->id,
            'customer_id' => Person::create(['business_id' => $bizB->id, 'first_name' => 'B', 'last_name' => 'B'])->id,
            'invoice_number' => 'INV-B1',
            'total_cents' => 10000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(30),
        ]);

        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $inv1 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-A1',
            'total_cents' => 10000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(10),
        ]);

        $inv2 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-A2',
            'total_cents' => 20000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(5),
        ]);

        $inv3 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-A3',
            'total_cents' => 30000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->addDays(5), // Not overdue
        ]);

        ArDunningAction::create([
            'business_id' => $biz->id,
            'invoice_id' => $inv1->id,
            'action' => 'email',
            'reason' => 'Customer promised to pay',
        ]);

        $screen = Livewire::actingAs($owner)->test(AgeingByReason::class)
            ->assertOk()
            ->assertSee('Customer promised to pay')
            ->assertSee('INV-A1')
            ->assertSee('10 days overdue')
            ->assertSeeHtml('wire:submit="logPayment('.$inv1->id.')"')
            ->assertSee('No reason recorded yet')
            ->assertSee('INV-A2')
            ->assertDontSee('INV-A3')
            ->assertDontSee('INV-B1')
            ->call('logPayment', $inv1->id)
            ->assertSee('This screen cannot take a photo yet')
            ->set('reference.'.$inv1->id, 'CHK-123')
            ->call('logPayment', $inv1->id)
            ->assertSee('Enter the amount that was paid');

        $this->assertSame(0, OfflinePayment::where('business_id', $biz->id)->count());

        $screen->set('amountCents.'.$inv1->id, 10000)
            ->call('logPayment', $inv1->id)
            ->assertSee('Payment logged')
            ->assertDontSee('INV-A1');

        $this->assertSame('paid', $inv1->fresh()->status);
        $this->assertSame(1, OfflinePayment::where('business_id', $biz->id)->where('reference_number', 'CHK-123')->count());

        Livewire::actingAs($owner)->test(AgeingByReason::class)
            ->set('reference.999999', 'x')
            ->set('amountCents.999999', 1)
            ->call('logPayment', 999999)
            ->assertSee("isn't in this account");

        $this->assertSame(1, OfflinePayment::where('business_id', $biz->id)->where('reference_number', 'CHK-123')->count());
    }

    public function test_draft_overdue_invoice_appears_on_ageing_screen(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Draft', 'last_name' => 'Overdue']);

        Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-DRAFT-1',
            'total_cents' => 40000,
            'paid_cents' => 0,
            'status' => 'draft',
            'due_date' => now()->subDays(2),
        ]);

        Livewire::actingAs($owner)->test(AgeingByReason::class)
            ->assertOk()
            ->assertSee('INV-DRAFT-1');
    }

    /**
     * G1-71 — the ageing screen is the fee door: a fee with no matching term is refused on the page, the term is written on the page (P-193), and a fee inside the term lands on the receivable
     */
    public function test_a_late_fee_is_refused_without_a_term_and_applied_inside_it(): void
    {
        Event::fake([ArFeeApplied::class, ArLateFeeTermSet::class]);

        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Tenancy::forgetUser();
        Livewire::test(AgeingByReason::class)->assertForbidden();
        Tenancy::setUser($owner->id);

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Late', 'last_name' => 'Payer']);
        $inv = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-F1',
            'total_cents' => 10000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(20),
        ]);

        $screen = Livewire::actingAs($owner)->test(AgeingByReason::class)
            ->assertOk()
            ->assertSee('no late-fee term in the agreement')
            ->assertSee('INV-F1')
            ->assertSeeHtml('wire:submit="applyLateFee('.$inv->id.')"')
            ->call('applyLateFee', $inv->id)
            ->assertSee('Enter the late fee in cents')
            ->set('feeCents.'.$inv->id, 2500)
            ->call('applyLateFee', $inv->id)
            ->assertSee('Late fee not applied')
            ->assertSee('a fee with no matching term is refused')
            ->assertSee('Write the term below, then apply the fee again.')
            ->assertDontSee('late fee 25.00');

        $this->assertSame(0, ReceivableState::where('business_id', $biz->id)->where('invoice_id', $inv->id)->count());
        $this->assertSame(0, ArPlanTerm::where('business_id', $biz->id)->count());
        Event::assertNotDispatched(ArFeeApplied::class);

        $screen->set('term.percent', 150)
            ->call('saveTerm')
            ->assertSee('between 1 and 100');

        $this->assertSame(0, ArPlanTerm::where('business_id', $biz->id)->count());
        Event::assertNotDispatched(ArLateFeeTermSet::class);

        $screen->set('term.percent', 10)
            ->set('term.cap', 2000)
            ->call('saveTerm')
            ->assertSee('Late-fee term saved: 10% of the invoice, capped at 20.00')
            ->assertSee('10% of the invoice, capped at 20.00')
            ->assertDontSee('no late-fee term in the agreement');

        $terms = ArPlanTerm::where('business_id', $biz->id)->firstOrFail();
        $this->assertSame(10, $terms->late_fee_percent);
        $this->assertSame(2000, $terms->late_fee_cap_cents);
        Event::assertDispatchedTimes(ArLateFeeTermSet::class, 1);

        $screen->set('feeCents.'.$inv->id, 2500)
            ->call('applyLateFee', $inv->id)
            ->assertSee('Late fee of 10.00 applied to INV-F1')
            ->assertSee('late fee 10.00');

        $state = ReceivableState::where('business_id', $biz->id)->where('invoice_id', $inv->id)->firstOrFail();
        $this->assertSame(1000, $state->late_fee_cents);
        $this->assertSame('overdue', $state->status);
        Event::assertDispatchedTimes(ArFeeApplied::class, 1);
    }

    public function test_automatic_chase_heading_does_not_render_rule_id(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Auto', 'last_name' => 'Chase']);
        $inv = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-AC1',
            'total_cents' => 10000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(5),
        ]);

        (new ProcessOverdueReceivable)->handle(new ArOverdue((int) $biz->id, (int) $inv->id, 5));

        $action = ArDunningAction::where('business_id', $biz->id)->where('invoice_id', $inv->id)->first();
        $this->assertSame('escalate_to_human', $action->action);

        Livewire::actingAs($owner)->test(AgeingByReason::class)
            ->assertOk()
            ->assertSee('nobody has recorded why this invoice is unpaid')
            ->assertDontSee('R211');
    }

    public function test_the_ageing_screen_says_nothing_is_past_its_terms_and_what_that_waits_on(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(AgeingByReason::class)
            ->assertOk()
            ->assertSee('No invoice is past its terms')
            ->assertSee('and a draft is never issued')
            ->assertDontSee('Every issued invoice is inside its terms');
    }

    public function test_the_ageing_screen_refuses_a_late_fee_once_the_invoice_is_at_the_term_ceiling(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Late', 'last_name' => 'Payer']);
        $inv = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-F2',
            'total_cents' => 100000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(20),
        ]);

        $screen = Livewire::actingAs($owner)->test(AgeingByReason::class)
            ->assertOk()
            ->set('term.percent', 10)
            ->set('term.cap', 2000)
            ->call('saveTerm')
            ->set('feeCents.'.$inv->id, 1500)
            ->call('applyLateFee', $inv->id)
            ->set('feeCents.'.$inv->id, 1500)
            ->call('applyLateFee', $inv->id)
            ->assertSee('late fee 20.00');

        $screen->set('feeCents.'.$inv->id, 1500)
            ->call('applyLateFee', $inv->id)
            ->assertSee('Late fee not applied')
            ->assertSee('is already at the term ceiling of 20.00')
            ->assertDontSee('late fee 35.00');

        $this->assertSame(2000, (int) ReceivableState::where('business_id', $biz->id)->where('invoice_id', $inv->id)->value('late_fee_cents'));
    }

    public function test_ageing_list_keys_each_invoice_row(): void
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

        $inv1 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-A1',
            'total_cents' => 10000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(10),
        ]);

        $inv2 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-A2',
            'total_cents' => 20000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(5),
        ]);

        Livewire::actingAs($owner)->test(AgeingByReason::class)
            ->assertOk()
            ->assertSeeHtml('wire:key="ageing-inv-'.$inv1->id.'"')
            ->assertSeeHtml('wire:key="ageing-inv-'.$inv2->id.'"');
    }

    public function test_a_logged_payment_does_not_leave_a_stale_late_fee_refusal_on_the_screen(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Stale', 'last_name' => 'Panel']);
        $inv = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-S1',
            'total_cents' => 10000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(20),
        ]);

        Livewire::actingAs($owner)->test(AgeingByReason::class)
            ->assertOk()
            ->set('feeCents.'.$inv->id, 2500)
            ->call('applyLateFee', $inv->id)
            ->assertSee('Late fee not applied')
            ->set('reference.'.$inv->id, 'CHK-77')
            ->set('amountCents.'.$inv->id, 10000)
            ->call('logPayment', $inv->id)
            ->assertSee('Payment logged')
            ->assertDontSee('Late fee not applied');
    }

    public function test_a_logged_payment_records_the_method_the_owner_chose(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Wire', 'last_name' => 'Payer']);
        $inv = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-M1',
            'total_cents' => 10000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(20),
        ]);

        Livewire::actingAs($owner)->test(AgeingByReason::class)
            ->assertOk()
            ->set('reference.'.$inv->id, 'WIRE-88')
            ->set('amountCents.'.$inv->id, 10000)
            ->set('paymentMethod.'.$inv->id, 'wire')
            ->call('logPayment', $inv->id)
            ->assertSee('Payment logged');

        $payment = OfflinePayment::where('business_id', $biz->id)->firstOrFail();
        $this->assertSame('wire', $payment->payment_method);
    }

    public function test_a_payment_method_outside_the_column_vocabulary_is_refused_and_writes_nothing(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Bogus', 'last_name' => 'Method']);
        $inv = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-M2',
            'total_cents' => 10000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(20),
        ]);

        Livewire::actingAs($owner)->test(AgeingByReason::class)
            ->assertOk()
            ->set('reference.'.$inv->id, 'REF-99')
            ->set('amountCents.'.$inv->id, 10000)
            ->set('paymentMethod.'.$inv->id, 'bitcoin')
            ->call('logPayment', $inv->id)
            ->assertSee('Choose how the payment arrived');

        $this->assertSame(0, OfflinePayment::where('business_id', $biz->id)->count());
    }

    public function test_a_reference_longer_than_the_column_is_refused_and_nothing_is_logged(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Long', 'last_name' => 'Ref']);
        $inv = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-L1',
            'total_cents' => 10000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(20),
        ]);

        Livewire::actingAs($owner)->test(AgeingByReason::class)
            ->set('reference.'.$inv->id, str_repeat('A', 256))
            ->set('amountCents.'.$inv->id, 10000)
            ->call('logPayment', $inv->id)
            ->assertSee('That reference is too long')
            ->assertSee('Keep it to 255 characters or fewer');

        $this->assertSame(0, OfflinePayment::where('business_id', $biz->id)->count());
    }

    public function test_a_refused_payment_names_the_payment_and_not_the_late_fee(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Space', 'last_name' => 'Case']);
        $inv = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-W1',
            'total_cents' => 10000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(20),
        ]);

        Livewire::actingAs($owner)->test(AgeingByReason::class)
            ->set('reference.'.$inv->id, '   ')
            ->set('amountCents.'.$inv->id, 10000)
            ->call('logPayment', $inv->id)
            ->assertSee('Payment not logged')
            ->assertSee('Nothing was logged')
            ->assertDontSee('Late fee not applied');

        $this->assertSame(0, OfflinePayment::where('business_id', $biz->id)->count());
    }

    public function test_a_cap_above_the_column_ceiling_is_refused_and_says_so(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);

        Livewire::actingAs($owner)->test(AgeingByReason::class)
            ->set('term.percent', 10)
            ->set('term.cap', 99999999999)
            ->call('saveTerm')
            ->assertSee('A cap of 99,999,999,999 cents is too large')
            ->assertSee('the most this can hold is 2,147,483,647 cents')
            ->assertDontSee('SQLSTATE');

        $this->assertSame(0, ArPlanTerm::where('business_id', $biz->id)->count());
    }

    public function test_a_part_paid_invoice_says_how_much_was_logged(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Part', 'last_name' => 'Payer']);
        $inv = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-P1',
            'total_cents' => 125000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(20),
        ]);

        Livewire::actingAs($owner)->test(AgeingByReason::class)
            ->assertOk()
            ->set('reference.'.$inv->id, 'CHK-P1')
            ->set('amountCents.'.$inv->id, 1250)
            ->call('logPayment', $inv->id)
            ->assertSee('Payment logged: 12.50.')
            ->assertDontSee('Payment logged.')
            ->assertSee('INV-P1');

        $this->assertSame(1250, OfflinePayment::where('business_id', $biz->id)->firstOrFail()->amount_cents);
        $this->assertSame('issued', $inv->fresh()->status);
    }
}
