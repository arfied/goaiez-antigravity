<?php

declare(strict_types=1);

namespace Tests\Modules\X205\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X205\Models\Affiliate;
use App\Modules\X205\Models\AffiliateAttribution;
use App\Modules\X205\Ui\EarningsView;
use App\Support\Tenancy;
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
}
