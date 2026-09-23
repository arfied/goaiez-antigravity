<?php

declare(strict_types=1);

namespace Tests\Modules\X218\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X218\Models\InfluencerDeal;
use App\Modules\X218\Models\InfluencerProfile;
use App\Modules\X218\Ui\DealTracker;
use App\Modules\X218\Ui\DiscoveryBoard;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class DealTrackerScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-218.deal-tracker'))->assertOk();

        Livewire::test(DealTracker::class)->assertOk();
    }

    public function test_can_create_deal(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(DiscoveryBoard::class)
            ->set('handle', 'super_star')
            ->call('discover')
            ->assertSet('error', null);

        $influencer = InfluencerProfile::where('business_id', $biz->id)->first();

        Livewire::test(DealTracker::class)
            ->set('influencerId', $influencer->id)
            ->set('dealAmountCents', 50000)
            ->call('createDeal')
            ->assertSet('success', 'Created active deal for 50000 cents. This feeds the tracker; nothing downstream is wired to it yet.');

        $this->assertDatabaseHas((new InfluencerDeal)->getTable(), [
            'business_id' => $biz->id,
            'influencer_id' => $influencer->id,
            'deal_amount_cents' => 50000,
            'status' => 'active',
            'is_paid' => false,
        ]);

        $this->get(route('x-218.deal-tracker'))
            ->assertSee('super_star - Amount: 50000 cents - Status: active');
    }

    public function test_refuses_empty_deal_input(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(DealTracker::class)
            ->call('createDeal')
            ->assertSet('error', 'Please select an influencer and enter an amount.');

        $this->assertDatabaseMissing((new InfluencerDeal)->getTable(), [
            'business_id' => $biz->id,
        ]);
    }
}
