<?php

declare(strict_types=1);

namespace Tests\Modules\X211;

use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X199\Models\Invoice;
use App\Modules\X211\Events\ArFeeApplied;
use App\Modules\X211\Events\ArLateFeeTermSet;
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
            ->assertSee('reference number or a photo')
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
            ->assertSee('No late-fee term in the agreement')
            ->assertSee('a fee with no matching term is refused')
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
}
