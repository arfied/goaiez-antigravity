<?php

declare(strict_types=1);

namespace Tests\Modules\X205\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X205\Models\Affiliate;
use App\Modules\X205\Models\AffiliatePayout;
use App\Modules\X205\Ui\AffiliatePortal;
use App\Modules\X205\Ui\EarningsView;
use App\Modules\X205\Ui\PayoutRunView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class PayoutRunViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-205.payout-run'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No payouts yet.');

        Tenancy::setUser($owner->id);
        $affiliate = Affiliate::create([
            'business_id' => $biz->id,
            'affiliate_code' => 'aff_distinctive_4603',
            'partner_name' => 'Distinctive Partner 4603',
            'commission_rate_bps' => 1250,
            'lifetime_earnings_cents' => 456000,
            'current_balance_cents' => 45600,
        ]);
        AffiliatePayout::create([
            'business_id' => $biz->id,
            'affiliate_id' => $affiliate->id,
            'amount_cents' => 123450,
            'status' => 'approved',
            'money_moved' => false,
        ]);
        Tenancy::forget();

        $this->get(route('x-205.payout-run'))
            ->assertOk()
            ->assertSee('1,234.50')
            ->assertSee('approved')
            ->assertSee('no money moved')
            ->assertDontSee('No payouts yet.');

        Livewire::test(PayoutRunView::class)
            ->set('affiliateId', $affiliate->id)
            ->set('amountCents', 5000)
            ->call('requestPayout')
            ->assertSet('success', "Requested payout of 5000 cents for affiliate #{$affiliate->id}. Status is 'requested'. No money moves.");

        $this->assertDatabaseHas((new AffiliatePayout)->getTable(), [
            'affiliate_id' => $affiliate->id,
            'amount_cents' => 5000,
            'status' => 'requested',
            'money_moved' => false,
        ]);

        $this->get(route('x-205.payout-run'))
            ->assertOk()
            ->assertSee('50.00')
            ->assertSee('requested');

        Livewire::test(PayoutRunView::class)
            ->set('affiliateId', 999999)
            ->set('amountCents', 1000)
            ->call('requestPayout')
            ->assertSet('error', 'Affiliate not found for the given ID.');

        Livewire::test(PayoutRunView::class)
            ->set('affiliateId', $affiliate->id)
            ->set('amountCents', 9999999)
            ->call('requestPayout')
            ->assertSet('error', 'Payout rejected: amount exceeds available current balance. Attribute a sale first if balance is zero.');
    }

    public function test_chain_create_attribute_payout(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::setUser($owner->id);

        // 1. Create affiliate on AffiliatePortal
        Livewire::test(AffiliatePortal::class)
            ->set('affiliateCode', 'chain_aff_1')
            ->set('partnerName', 'Chain Partner 1')
            ->set('commissionRateBps', 2000)
            ->call('createAffiliate');

        $affiliate = Affiliate::where('business_id', $biz->id)->where('affiliate_code', 'chain_aff_1')->firstOrFail();

        // 2. Attribute sale on EarningsView
        Livewire::test(EarningsView::class)
            ->set('affiliateCode', 'chain_aff_1')
            ->set('orderId', 'chain_order_1')
            ->set('saleAmountCents', 10000)
            ->call('attributeSale');

        // 3. Request payout on PayoutRunView
        Livewire::test(PayoutRunView::class)
            ->set('affiliateId', $affiliate->id)
            ->set('amountCents', 1500)
            ->call('requestPayout');

        Tenancy::forget();

        // 4. GET on each of the three routes
        $this->get(route('x-205.portal'))
            ->assertOk()
            ->assertSee('Chain Partner 1');

        $this->get(route('x-205.earnings'))
            ->assertOk()
            ->assertSee('chain_order_1')
            ->assertSee('2000 cents on 10000');

        $this->get(route('x-205.payout-run'))
            ->assertOk()
            ->assertSee('15.00')
            ->assertSee('requested');
    }
}
