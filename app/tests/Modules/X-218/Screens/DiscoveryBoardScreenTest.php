<?php

declare(strict_types=1);

namespace Tests\Modules\X218\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X218\Ui\DiscoveryBoard;
use Livewire\Livewire;
use Tests\TestCase;

class DiscoveryBoardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-218.discovery-board'))->assertOk();

        Livewire::test(DiscoveryBoard::class)->assertOk();
    }

    public function test_can_discover_influencer(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-218.discovery-board'))
            ->assertSee('No influencers discovered yet.');

        Livewire::test(DiscoveryBoard::class)
            ->set('handle', 'test_handle')
            ->set('platform', 'instagram')
            ->set('audienceSize', 1000)
            ->set('engagementRate', 5.5)
            ->call('discover')
            ->assertSet('error', null)
            ->assertSet('success', 'Discovered test_handle on instagram. This feeds the influencer lists; nothing downstream is wired to it yet.');

        $this->assertDatabaseHas('influencer_profiles', [
            'business_id' => $biz->id,
            'handle' => 'test_handle',
            'platform' => 'instagram',
            'audience_size' => 1000,
            'engagement_rate' => 5.5,
        ]);

        $this->get(route('x-218.discovery-board'))
            ->assertDontSee('No influencers discovered yet.')
            ->assertSee('test_handle');
    }

    public function test_refuses_empty_handle(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(DiscoveryBoard::class)
            ->set('handle', '   ')
            ->call('discover')
            ->assertSet('error', 'Handle cannot be empty.');

        $this->assertDatabaseMissing('influencer_profiles', [
            'business_id' => $biz->id,
        ]);
    }
}
