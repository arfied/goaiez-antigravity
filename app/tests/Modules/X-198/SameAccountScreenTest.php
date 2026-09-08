<?php

declare(strict_types=1);

namespace Tests\Modules\X198;

use App\Models\User;
use App\Modules\X198\Actions\PayoutReconcileAction;
use App\Modules\X198\Models\MerchantConnection;
use App\Modules\X198\Models\Payment;
use App\Modules\X198\Models\Payout;
use App\Modules\X198\Ui\SameAccount;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class SameAccountScreenTest extends TestCase
{
    public function test_same_account_shows_where_money_lands_and_attaches_a_detached_payment_once()
    {
        $biz = self::provisionTenant();
        $bizB = self::provisionTenant();

        Tenancy::set($bizB->id);
        $connB = MerchantConnection::create(['business_id' => $bizB->id, 'gateway_name' => 'square', 'merchant_account_id' => 'acct_B9', 'is_connected' => true]);
        Payment::create(['business_id' => $bizB->id, 'merchant_connection_id' => $connB->id, 'amount_cents' => 9900, 'currency' => 'USD', 'payment_token' => 'sq_tok_b', 'idempotency_key' => 'idem_b', 'status' => 'pending']);

        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $connA = MerchantConnection::create(['business_id' => $biz->id, 'gateway_name' => 'square', 'merchant_account_id' => 'acct_A1', 'is_connected' => true]);
        Payment::create(['business_id' => $biz->id, 'merchant_connection_id' => $connA->id, 'amount_cents' => 3000, 'currency' => 'USD', 'payment_token' => 'sq_tok_1', 'idempotency_key' => 'idem_a1', 'status' => 'pending']);
        Payment::create(['business_id' => $biz->id, 'merchant_connection_id' => $connA->id, 'amount_cents' => 4500, 'currency' => 'USD', 'payment_token' => 'sq_tok_2', 'idempotency_key' => 'idem_a2', 'status' => 'pending']);

        $loose = Payment::create(['business_id' => $biz->id, 'merchant_connection_id' => null, 'amount_cents' => 2500, 'currency' => 'USD', 'payment_token' => 'sq_tok_loose', 'idempotency_key' => 'idem_loose', 'status' => 'pending']);

        $payout = Payout::create(['business_id' => $biz->id, 'merchant_connection_id' => $connA->id, 'gateway_payout_id' => 'po_a_1', 'amount_cents' => 5000, 'status' => 'pending', 'payout_date' => now()->toDateString()]);
        app(PayoutReconcileAction::class)->handle($biz->id, $payout->id, 5000);

        Tenancy::forgetUser();
        Livewire::test(SameAccount::class)->assertForbidden();
        Tenancy::setUser($owner->id);

        $screen = Livewire::actingAs($owner)->test(SameAccount::class)
            ->assertOk()
            ->assertSee('acct_A1')
            ->assertSee('Recorded merchant account')
            ->assertSee('2 payments · 75.00')
            ->assertSee('1 payouts · 50.00')
            ->assertSee('balanced')
            ->assertDontSee('acct_B9')
            ->assertDontSee('99.00')
            ->assertSee('Not attached to any account')
            ->assertSee('25.00')
            ->assertSee('idem_loose')
            ->assertSeeHtml('wire:click="attach('.$loose->id.', '.$connA->id.')"')
            ->call('attach', $loose->id, $connA->id)
            ->assertSee('25.00 is now recorded against acct_A1')
            ->assertDontSee('Not attached to any account')
            ->assertSee('3 payments · 100.00')
            ->call('attach', $loose->id, $connA->id)
            ->assertSee('already recorded against acct_A1')
            ->call('pull', $connA->id)
            ->assertSee('waits on its payout read path')
            ->call('attach', 999999, $connA->id)
            ->assertSee("isn't in this account");

        $this->assertSame($connA->id, $loose->fresh()->merchant_connection_id);
        Tenancy::set($bizB->id);
        $this->assertSame(1, Payment::where('business_id', $bizB->id)->count());
    }

    public function test_the_same_account_screen_says_where_a_charge_actually_lands()
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $connA = MerchantConnection::create(['business_id' => $biz->id, 'gateway_name' => 'square', 'merchant_account_id' => 'acct_A1', 'is_connected' => true]);
        Payment::create(['business_id' => $biz->id, 'merchant_connection_id' => null, 'amount_cents' => 2500, 'currency' => 'USD', 'payment_token' => 'sq_tok_loose', 'idempotency_key' => 'idem_loose', 'status' => 'pending']);

        Livewire::actingAs($owner)->test(SameAccount::class)
            ->assertOk()
            ->assertSee('are taken on the goaiez platform Stripe account')
            ->assertSee('which no charge is routed to yet')
            ->assertSee('no account recorded')
            ->assertDontSee('never the platform')
            ->assertSee('recorded only')
            ->assertDontSee('connected');
    }

    public function test_the_same_account_empty_state_names_what_it_waits_on()
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(SameAccount::class)
            ->assertOk()
            ->assertSee('Recording one waits on the Stripe Connect redirect')
            ->assertSee('no payout has ever been imported')
            ->assertDontSee('payouts wait on the same import');
    }

    public function test_the_same_account_screen_offers_to_check_for_payouts_and_never_to_pull_them(): void
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $connA = MerchantConnection::create(['business_id' => $biz->id, 'gateway_name' => 'stripe', 'merchant_account_id' => 'acct_A1', 'is_connected' => true]);

        Livewire::actingAs($owner)->test(SameAccount::class)
            ->assertOk()
            ->assertSee('Check stripe for payouts')
            ->assertDontSee('Pull payouts')
            ->call('pull', $connA->id)
            ->assertSee('nothing was pulled and nothing changed');
    }
}
