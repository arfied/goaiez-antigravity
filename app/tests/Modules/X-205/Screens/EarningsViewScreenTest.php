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

        Livewire::test(EarningsView::class)->assertOk();
    }
}
