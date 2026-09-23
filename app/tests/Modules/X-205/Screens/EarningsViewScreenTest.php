<?php

declare(strict_types=1);

namespace Tests\Modules\X205\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X205\Models\Affiliate;
use App\Modules\X205\Models\AffiliateAttribution;
use App\Modules\X205\Ui\EarningsView;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

class EarningsViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-205.earnings'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No commissions earned yet.');

        Tenancy::setUser($owner->id);
        $affiliate = Affiliate::create([
            'business_id' => $biz->id,
            'affiliate_code' => 'aff_distinctive_4495',
            'partner_name' => 'Distinctive Partner 4495',
            'commission_rate_bps' => 1000,
            'lifetime_earnings_cents' => 0,
            'current_balance_cents' => 0,
        ]);
        AffiliateAttribution::create([
            'business_id' => $biz->id,
            'affiliate_id' => $affiliate->id,
            'order_id' => 'order_distinctive_4495',
            'sale_amount_cents' => 449500,
            'commission_cents' => 44950,
            'attributed_at' => now(),
        ]);
        Tenancy::forget();

        $this->get(route('x-205.earnings'))
            ->assertOk()
            ->assertSee('order_distinctive_4495')
            ->assertSee('44950 cents on 449500')
            ->assertSee('[none]')
            ->assertDontSee('No commissions earned yet.');

        Livewire::test(EarningsView::class)
            ->set('affiliateCode', 'aff_distinctive_4495')
            ->set('orderId', 'new_order_123')
            ->set('saleAmountCents', 50000)
            ->call('attributeSale')
            ->assertSet('success', "Attributed order new_order_123 to affiliate aff_distinctive_4495. Commission is 5000 cents. This feeds the earnings view and updates the affiliate's balance; nothing downstream is wired to it yet.");

        $this->assertDatabaseHas((new AffiliateAttribution)->getTable(), [
            'affiliate_id' => $affiliate->id,
            'order_id' => 'new_order_123',
            'sale_amount_cents' => 50000,
            'commission_cents' => 5000,
        ]);

        $this->get(route('x-205.earnings'))
            ->assertOk()
            ->assertSee('new_order_123')
            ->assertSee('5000 cents on 50000');

        $this->get(route('x-205.portal'))
            ->assertOk()
            ->assertSee('balance 5000 cents')
            ->assertSee('lifetime 5000 cents');

        Livewire::test(EarningsView::class)
            ->set('affiliateCode', 'invalid_code_999')
            ->set('orderId', 'failed_order_123')
            ->set('saleAmountCents', 10000)
            ->call('attributeSale')
            ->assertSet('error', 'Affiliate not found for the given code.');

        $this->assertDatabaseMissing((new AffiliateAttribution)->getTable(), [
            'order_id' => 'failed_order_123',
        ]);
    }

    public function test_can_propose_a_clawback(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::setUser($owner->id);
        $affiliate = Affiliate::create([
            'business_id' => $biz->id,
            'affiliate_code' => 'aff_clawback_1',
            'partner_name' => 'Clawback Partner 1',
            'commission_rate_bps' => 1000,
            'lifetime_earnings_cents' => 0,
            'current_balance_cents' => 0,
        ]);
        Tenancy::set((int) $biz->id);

        Livewire::test(EarningsView::class)
            ->set('affiliateCode', 'aff_clawback_1')
            ->set('orderId', 'order_clawback_1')
            ->set('saleAmountCents', 100000)
            ->call('attributeSale');

        $attribution = AffiliateAttribution::where('order_id', 'order_clawback_1')->firstOrFail();

        Livewire::test(EarningsView::class)
            ->set('clawbackAttributionId', (string) $attribution->id)
            ->set('clawbackDisputeRef', 'DISPUTE-4471')
            ->call('proposeClawback')
            ->assertSet('clawbackSuccess', 'Clawback proposed on order order_clawback_1. No money has moved — the 10000 cent commission is unchanged. Nothing approves a proposal yet and nobody is notified, so it stays proposed.');

        $this->assertDatabaseHas((new AffiliateAttribution)->getTable(), [
            'id' => $attribution->id,
            'clawback_status' => 'proposed',
            'is_clawed_back' => false,
            'triggering_dispute_ref' => 'DISPUTE-4471',
        ]);
    }

    public function test_the_earnings_line_shows_the_proposal(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::setUser($owner->id);
        $affiliate = Affiliate::create([
            'business_id' => $biz->id,
            'affiliate_code' => 'aff_line_1',
            'partner_name' => 'Line Partner 1',
            'commission_rate_bps' => 1000,
            'lifetime_earnings_cents' => 0,
            'current_balance_cents' => 0,
        ]);
        Tenancy::set((int) $biz->id);

        Livewire::test(EarningsView::class)
            ->set('affiliateCode', 'aff_line_1')
            ->set('orderId', 'order_line_1')
            ->set('saleAmountCents', 200000)
            ->call('attributeSale');

        $attribution = AffiliateAttribution::where('order_id', 'order_line_1')->firstOrFail();

        Livewire::test(EarningsView::class)
            ->set('clawbackAttributionId', (string) $attribution->id)
            ->call('proposeClawback');

        Tenancy::forget();

        $this->get(route('x-205.earnings'))
            ->assertOk()
            ->assertSee('order_line_1: 20000 cents on 200000 [proposed]')
            ->assertDontSee('order_line_1: 20000 cents on 200000 [none]');
    }

    public function test_refuses_with_no_commission_chosen(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::setUser($owner->id);
        $affiliate = Affiliate::create([
            'business_id' => $biz->id,
            'affiliate_code' => 'aff_empty_1',
            'partner_name' => 'Empty Partner 1',
            'commission_rate_bps' => 1000,
            'lifetime_earnings_cents' => 0,
            'current_balance_cents' => 0,
        ]);
        Tenancy::set((int) $biz->id);

        Livewire::test(EarningsView::class)
            ->set('affiliateCode', 'aff_empty_1')
            ->set('orderId', 'order_empty_1')
            ->set('saleAmountCents', 100000)
            ->call('attributeSale');

        $attribution = AffiliateAttribution::where('order_id', 'order_empty_1')->firstOrFail();

        Livewire::test(EarningsView::class)
            ->set('clawbackAttributionId', '')
            ->call('proposeClawback')
            ->assertSet('clawbackError', 'Choose the commission to claw back.');

        $this->assertDatabaseHas((new AffiliateAttribution)->getTable(), [
            'id' => $attribution->id,
            'clawback_status' => 'none',
        ]);
    }

    public function test_proposing_on_another_tenants_commission_is_refused(): void
    {
        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);
        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);

        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);
        $this->actingAs($ownerB);

        $affiliate = Affiliate::create([
            'business_id' => $bizB->id,
            'affiliate_code' => 'aff_tenant_b',
            'partner_name' => 'Tenant B Partner',
            'commission_rate_bps' => 1000,
            'lifetime_earnings_cents' => 0,
            'current_balance_cents' => 0,
        ]);
        Livewire::test(EarningsView::class)
            ->set('affiliateCode', 'aff_tenant_b')
            ->set('orderId', 'order_tenant_b')
            ->set('saleAmountCents', 100000)
            ->call('attributeSale');
        $attrB = AffiliateAttribution::where('order_id', 'order_tenant_b')->firstOrFail();

        Tenancy::setUser($ownerA->id);
        Tenancy::set((int) $bizA->id);
        $this->actingAs($ownerA);

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(EarningsView::class)
            ->set('clawbackAttributionId', (string) $attrB->id)
            ->call('proposeClawback');
    }
}
