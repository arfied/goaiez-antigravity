<?php

declare(strict_types=1);

namespace Tests\Modules\CBilling;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CBilling\Models\CreditLedgerEntry;
use App\Modules\CBilling\Ui\Credits;
use App\Modules\X198\Events\PaymentCaptured;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentCapturedFlowTest extends TestCase
{
    public function test_payment_captured_tops_up_ledger_and_shows_on_credits_screen(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        event(new PaymentCaptured(
            businessId: $biz->id,
            paymentId: 999,
            gatewayChargeId: 'ch_123',
            amountCents: 5000,
            invoiceId: 100
        ));

        $entry = CreditLedgerEntry::where('business_id', $biz->id)
            ->where('entry_type', 'topup')
            ->first();
        
        $this->assertNotNull($entry);
        $this->assertEquals(500000, $entry->amount_hundredths_cents);

        $response = $this->get('/app/c-billing/credits');
        $response->assertOk();
        $response->assertSee('50.0000');
        
        Livewire::test(Credits::class)->assertSee('50.0000');
    }

    public function test_zero_amount_payment_captured_writes_no_row(): void
    {
        $biz = $this->provisionTenant();
        
        event(new PaymentCaptured(
            businessId: $biz->id,
            paymentId: 999,
            gatewayChargeId: 'ch_123',
            amountCents: 0,
            invoiceId: 100
        ));

        $count = CreditLedgerEntry::where('business_id', $biz->id)->count();
        $this->assertEquals(0, $count);
    }
}
