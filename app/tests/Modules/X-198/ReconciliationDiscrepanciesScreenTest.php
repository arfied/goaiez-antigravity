<?php

declare(strict_types=1);

namespace Tests\Modules\X198;

use App\Models\User;
use App\Modules\X198\Actions\PayoutReconcileAction;
use App\Modules\X198\Models\MerchantConnection;
use App\Modules\X198\Models\Payout;
use App\Modules\X198\Models\ReconciliationRun;
use App\Modules\X198\Ui\ReconciliationDiscrepancies;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ReconciliationDiscrepanciesScreenTest extends TestCase
{
    public function test_discrepancies_shows_only_mismatched_runs_and_reviews_never_corrects()
    {
        $biz = self::provisionTenant();
        $bizB = self::provisionTenant();

        Tenancy::set($bizB->id);
        $connB = MerchantConnection::create(['business_id' => $bizB->id, 'gateway_name' => 'square', 'merchant_account_id' => 'acct_B9', 'is_connected' => true]);
        $payoutB = Payout::create(['business_id' => $bizB->id, 'merchant_connection_id' => $connB->id, 'gateway_payout_id' => 'po_other_9', 'amount_cents' => 900, 'status' => 'pending', 'payout_date' => now()->toDateString()]);
        app(PayoutReconcileAction::class)->handle($bizB->id, $payoutB->id, 1000);

        $owner = User::findOrFail($biz->owner_user_id);
        $owner->forceFill(['name' => "Mariano O'Connell"])->save();
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        $connA = MerchantConnection::create(['business_id' => $biz->id, 'gateway_name' => 'square', 'merchant_account_id' => 'acct_A1', 'is_connected' => true]);
        $payoutShort = Payout::create(['business_id' => $biz->id, 'merchant_connection_id' => $connA->id, 'gateway_payout_id' => 'po_short_1', 'amount_cents' => 4900, 'status' => 'pending', 'payout_date' => now()->toDateString()]);
        $short = app(PayoutReconcileAction::class)->handle($biz->id, $payoutShort->id, 5000)['run_id'];

        $payoutBalanced = Payout::create(['business_id' => $biz->id, 'merchant_connection_id' => $connA->id, 'gateway_payout_id' => 'po_balanced_1', 'amount_cents' => 7000, 'status' => 'pending', 'payout_date' => now()->toDateString()]);
        $balanced = app(PayoutReconcileAction::class)->handle($biz->id, $payoutBalanced->id, 7000)['run_id'];

        Tenancy::forgetUser();
        Livewire::test(ReconciliationDiscrepancies::class)->assertForbidden();
        Tenancy::setUser($owner->id);

        $screen = Livewire::actingAs($owner)->test(ReconciliationDiscrepancies::class)
            ->assertOk()
            ->assertSee('One account at a time')
            ->assertSee('po_short_1')
            ->assertSee('50.00')
            ->assertSee('49.00')
            ->assertSee('-1.00')
            ->assertSee('Mismatched payout')
            ->assertSee('unreviewed')
            ->assertDontSee('po_balanced_1')
            ->assertDontSee('po_other_9')
            ->assertSeeHtml('wire:click="review('.$short.')"')
            ->call('review', $short)
            ->assertSee('Reviewed: payout po_short_1 stays 1.00 off')
            ->assertSee('reviewed by '.$owner->name)
            ->assertDontSee('unreviewed');

        $run = ReconciliationRun::findOrFail($short);
        $this->assertSame($owner->id, $run->reviewed_by_user_id);
        $this->assertNotNull($run->reviewed_at);
        $this->assertSame(-100, $run->discrepancy_cents, 'reviewed, never corrected');
        $first = $run->reviewed_at->toIso8601String();

        $screen->call('review', $short);
        $this->assertSame($first, ReconciliationRun::findOrFail($short)->reviewed_at->toIso8601String(), 'a second review changes nothing');

        $screen->call('review', $balanced)
            ->assertSee('Nothing to review')
            ->call('review', 999999)
            ->assertSee("isn't in this account");

        $this->assertSame(2, ReconciliationRun::where('business_id', $biz->id)->count());
    }

    public function test_the_empty_screen_says_payouts_are_not_imported_yet()
    {
        $biz = self::provisionTenant();
        $owner = User::findOrFail($biz->owner_user_id);
        Tenancy::set($biz->id);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(ReconciliationDiscrepancies::class)
            ->assertOk()
            ->assertSee('No payouts have been imported yet.')
            ->assertSee('not connected yet');
    }
}
