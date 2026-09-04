<?php

declare(strict_types=1);

namespace Tests\Modules\X211;

use App\Models\User;
use App\Modules\X199\Models\Invoice;
use App\Modules\X121\Models\Person;
use App\Modules\X211\Ui\AgeingByReason;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AgeingByReasonScreenTest extends TestCase
{
    public function test_ageing_by_reason_groups_and_logs_payment(): void
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

        $inv3 = Invoice::create([
            'business_id' => $biz->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-A3',
            'total_cents' => 30000,
            'paid_cents' => 0,
            'status' => 'issued',
            'due_date' => now()->addDays(5), // Not overdue
        ]);

        DB::table('ar_dunning_actions')->insert([
            'business_id' => $biz->id,
            'invoice_id' => $inv1->id,
            'action' => 'email',
            'reason' => 'Customer promised to pay',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($owner)->test(AgeingByReason::class)
            ->assertSee('Customer promised to pay')
            ->assertSee('INV-A1')
            ->assertSee('No reason recorded yet')
            ->assertSee('INV-A2')
            ->assertDontSee('INV-A3')
            ->set('referenceNumber', '')
            ->call('logPayment', $inv1->id)
            ->assertHasErrors(['referenceNumber'])
            ->set('referenceNumber', 'CHK-123')
            ->call('logPayment', $inv1->id)
            ->assertHasNoErrors();
            
        $this->assertEquals(10000, $inv1->fresh()->paid_cents);
        $this->assertEquals('paid', $inv1->fresh()->status);
    }
}
