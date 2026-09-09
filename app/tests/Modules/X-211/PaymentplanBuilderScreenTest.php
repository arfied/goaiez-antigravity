<?php

declare(strict_types=1);

namespace Tests\Modules\X211;

use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X199\Models\Invoice;
use App\Modules\X211\Events\ArPlanAccepted;
use App\Modules\X211\Models\ArPlanTerm;
use App\Modules\X211\Models\PaymentPlan;
use App\Modules\X211\Models\ReceivableState;
use App\Modules\X211\Ui\PaymentplanBuilder;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentplanBuilderScreenTest extends TestCase
{
    public function test_paymentplan_builder_offers_inside_the_threshold_and_routes_past_it(): void
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

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'John', 'last_name' => 'Doe']);

        $inv1 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-A1',
            'total_cents' => 90000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(10),
        ]);

        Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-A2',
            'total_cents' => 5000,
            'paid_cents' => 5000,
            'status' => 'paid',
            'due_date' => now()->subDays(3),
        ]);

        Event::fake([ArPlanAccepted::class]);

        $screen = Livewire::actingAs($owner)->test(PaymentplanBuilder::class)
            ->assertOk()
            ->assertSee('Up to 3 payments over 90 days')
            ->assertSee('INV-A1')
            ->assertDontSee('INV-A2')
            ->assertDontSee('INV-B1')
            ->assertSeeHtml('wire:submit="offerPlan('.$inv1->id.')"')
            ->set('installments.'.$inv1->id, 12)
            ->call('offerPlan', $inv1->id)
            ->assertSee('12 monthly payments over 360 days is credit')
            ->assertSee('waits on a financing partner');

        $this->assertSame(0, PaymentPlan::where('business_id', $biz->id)->count());
        $this->assertSame(1, ArPlanTerm::where('business_id', $biz->id)->count());
        Event::assertNotDispatched(ArPlanAccepted::class);

        $screen->set('installments.'.$inv1->id, 3)
            ->set('frequency.'.$inv1->id, 'monthly')
            ->call('offerPlan', $inv1->id)
            ->assertSee('Plan recorded on INV-A1: 3 monthly payments of 300.00')
            ->assertSee('The customer has not been told: nothing in this module sends anything')
            ->assertSee('Plans in place')
            ->assertSee('offered')
            ->assertDontSee('accepted');

        $plan = PaymentPlan::where('business_id', $biz->id)->where('invoice_id', $inv1->id)->firstOrFail();
        $this->assertSame(30000, $plan->installment_amount_cents);
        $this->assertSame('payment_plan', ReceivableState::where('business_id', $biz->id)->where('invoice_id', $inv1->id)->value('status'));
        Event::assertDispatched(ArPlanAccepted::class);

        Livewire::actingAs($owner)->test(PaymentplanBuilder::class)
            ->assertSee('Nothing to split')
            ->assertSee('and a draft is never issued')
            ->assertDontSee('Every open invoice is paid or already on a plan')
            ->set('installments.999999', 3)
            ->call('offerPlan', 999999)
            ->assertSee("isn't in this account");
    }

    public function test_invoice_with_paid_cents_equal_to_total_cents_is_not_offered_plan(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $customer = Person::create(['business_id' => $biz->id, 'first_name' => 'Paid', 'last_name' => 'Full']);

        Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-PAID-1',
            'total_cents' => 5000,
            'paid_cents' => 5000,
            'status' => 'issued',
            'due_date' => now()->subDays(3),
        ]);

        Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-UNPAID-1',
            'total_cents' => 5000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(3),
        ]);

        Livewire::actingAs($owner)->test(PaymentplanBuilder::class)
            ->assertOk()
            ->assertSee('INV-UNPAID-1')
            ->assertDontSee('INV-PAID-1');
    }

    public function test_rendering_the_plan_builder_writes_no_plan_term_row(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $this->assertSame(0, ArPlanTerm::where('business_id', $biz->id)->count());

        Livewire::actingAs($owner)->test(PaymentplanBuilder::class)
            ->assertOk()
            ->assertSee('Up to 3 payments over 90 days');

        $this->assertSame(0, ArPlanTerm::where('business_id', $biz->id)->count());
    }
}
