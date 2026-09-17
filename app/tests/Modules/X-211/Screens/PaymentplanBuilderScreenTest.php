<?php

declare(strict_types=1);

namespace Tests\Modules\X211\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X199\Models\Invoice;
use App\Modules\X211\Models\PaymentPlan;
use App\Modules\X211\Ui\PaymentplanBuilder;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentplanBuilderScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-211.paymentplan-builder'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Nothing to split.');

        Tenancy::setUser($owner->id);
        Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => null,
            'invoice_number' => 'Distinctive INV-4612',
            'total_cents' => 30000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => today()->addDays(10),
        ]);
        $invoice2 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => null,
            'invoice_number' => 'Distinctive INV-4613',
            'total_cents' => 60000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => today()->addDays(20),
        ]);
        PaymentPlan::create([
            'business_id' => $biz->id,
            'invoice_id' => $invoice2->id,
            'installments_count' => 3,
            'installment_amount_cents' => 20000,
            'frequency' => 'monthly',
            'status' => 'offered',
        ]);
        Tenancy::forget();

        $this->get(route('x-211.paymentplan-builder'))
            ->assertOk()
            ->assertSee('Distinctive INV-4612')
            ->assertSee('300.00 owed')
            ->assertSee('about 100.00 each')
            ->assertSee('Distinctive INV-4613')
            ->assertSee('3 × 200.00 monthly')
            ->assertDontSee('Nothing to split.');

        Livewire::test(PaymentplanBuilder::class)->assertOk();
    }
}
