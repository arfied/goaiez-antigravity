<?php

declare(strict_types=1);

namespace Tests\Modules\X205\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X205\Models\Affiliate;
use App\Modules\X205\Ui\AffiliatePortal;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class AffiliatePortalScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-205.portal'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No affiliates yet.');

        Tenancy::setUser($owner->id);
        Affiliate::create([
            'business_id' => $biz->id,
            'affiliate_code' => 'aff_distinctive_4560',
            'partner_name' => 'Distinctive Partner 4560',
            'commission_rate_bps' => 1250,
            'lifetime_earnings_cents' => 456000,
            'current_balance_cents' => 45600,
        ]);
        Tenancy::forget();

        $this->get(route('x-205.portal'))
            ->assertOk()
            ->assertSee('Distinctive Partner 4560')
            ->assertSee('(aff_distinctive_4560)')
            ->assertSee('1250 bps')
            ->assertSee('balance 45600 cents')
            ->assertDontSee('No affiliates yet.');

        Livewire::test(AffiliatePortal::class)
            ->set('affiliateCode', 'new_aff_789')
            ->set('partnerName', 'New Partner 789')
            ->set('commissionRateBps', 1500)
            ->call('createAffiliate')
            ->assertSet('success', 'Created affiliate New Partner 789 with code new_aff_789 at 1500 bps. Balance starts at zero. This feeds the affiliate portal; nothing downstream is wired to it yet.');

        $this->assertDatabaseHas((new Affiliate)->getTable(), [
            'affiliate_code' => 'new_aff_789',
            'partner_name' => 'New Partner 789',
            'commission_rate_bps' => 1500,
            'current_balance_cents' => 0,
            'lifetime_earnings_cents' => 0,
        ]);

        $this->get(route('x-205.portal'))
            ->assertOk()
            ->assertSee('New Partner 789')
            ->assertSee('(new_aff_789)')
            ->assertSee('1500 bps');

        Livewire::test(AffiliatePortal::class)
            ->set('affiliateCode', ' ')
            ->set('partnerName', 'Missing Name')
            ->call('createAffiliate')
            ->assertSet('error', 'Affiliate code and partner name are required.');

        $this->assertDatabaseMissing((new Affiliate)->getTable(), [
            'partner_name' => 'Missing Name',
        ]);
    }
}
