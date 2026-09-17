<?php

declare(strict_types=1);

namespace Tests\Modules\X205\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X205\Models\Affiliate;
use App\Modules\X205\Models\AffiliatePayout;
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

        Livewire::test(PayoutRunView::class)->assertOk();
    }
}
