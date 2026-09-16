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

        Livewire::test(AffiliatePortal::class)->assertOk();
    }
}
