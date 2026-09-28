<?php

declare(strict_types=1);

namespace Tests\Modules\X01\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X01\Ui\PaymentRisk;
use App\Modules\X121\Models\Person;
use App\Modules\X199\Models\Invoice;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentRiskScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-01.payment-risk'))->assertOk();

        Livewire::test(PaymentRisk::class)->assertOk();
    }

    public function test_overdue_invoices_and_dunning_record(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $customer = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'Wenda',
            'last_name' => 'Okonkwo',
        ]);

        $invoice1 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-40',
            'total_cents' => 10000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(40)->toDateString(),
        ]);

        $customer2 = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $invoice2 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer2->id,
            'invoice_number' => 'INV-5',
            'total_cents' => 5000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->get(route('x-01.payment-risk'))
            ->assertOk()
            ->assertSee('Your account', false)
            ->assertSee('Wenda Okonkwo', false)
            ->assertSee('INV-40', false)
            ->assertSee('INV-5', false)
            ->assertSee('High', false)
            ->assertSee('Watch', false);

        Livewire::test(PaymentRisk::class)
            ->call('recordReason', $invoice1->id, 'promised');

        $this->assertDatabaseHas('ar_dunning_actions', [
            'business_id' => $biz->id,
            'invoice_id' => $invoice1->id,
            'action' => 'reason_recorded',
        ]);

        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);
        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);

        try {
            Livewire::test(PaymentRisk::class)
                ->call('recordReason', $invoice1->id, 'promised');
            $this->fail('Expected 404 ModelNotFoundException when acting as another tenant.');
        } catch (ModelNotFoundException $e) {
            // It correctly threw a 404.
        }

        $this->assertDatabaseMissing('ar_dunning_actions', [
            'invoice_id' => $invoice1->id,
            'reason' => 'promised',
            'business_id' => $bizB->id,
        ]);
    }

    public function test_the_reason_picker_binds_no_property_the_screen_lacks(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $customer = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'Wenda',
            'last_name' => 'Okonkwo',
        ]);

        $invoice1 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-40',
            'total_cents' => 10000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(40)->toDateString(),
        ]);

        $customer2 = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $invoice2 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer2->id,
            'invoice_number' => 'INV-5',
            'total_cents' => 5000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        Livewire::test(PaymentRisk::class)
            ->assertSeeHtml('<select')
            ->assertSeeHtml('recordReason('.$invoice1->id)
            ->assertDontSeeHtml('wire:model="reasonCode_');
    }
}
