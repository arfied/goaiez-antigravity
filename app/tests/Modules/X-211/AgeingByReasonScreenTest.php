<?php

declare(strict_types=1);

namespace Tests\Modules\X211;

use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X199\Models\Invoice;
use App\Modules\X211\Models\ArDunningAction;
use App\Modules\X211\Models\OfflinePayment;
use App\Modules\X211\Ui\AgeingByReason;
use App\Support\Tenancy;
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

        Livewire::actingAs($owner)->test(AgeingByReason::class)
            ->assertOk()
            ->assertSee('Customer promised to pay')
            ->assertSee('INV-A1')
            ->assertSee('10 days overdue')
            ->assertSee('No reason recorded yet')
            ->assertSee('INV-A2')
            ->assertDontSee('INV-A3')
            ->assertDontSee('INV-B1')
            ->call('logPayment', $inv1->id)
            ->assertSee('reference number or a photo')
            ->set('reference.'.$inv1->id, 'CHK-123')
            ->set('amountCents.'.$inv1->id, 10000)
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
}
