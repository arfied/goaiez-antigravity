<?php

declare(strict_types=1);

namespace Tests\Modules\X139\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X139\Models\AdConnection;
use App\Modules\X139\Ui\AdaccountConnectCard;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class AdaccountConnectCardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-139.adaccount-connect-card'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('<h1 class="sr-only">Ad Platform Connections</h1>', false);

        Livewire::test(AdaccountConnectCard::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-139.adaccount-connect-card.admin'))->assertOk();

        Livewire::test(AdaccountConnectCard::class)->assertOk();
    }

    public function test_component_shows_connected_state_and_isolates_tenants(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        $otherOwner = User::factory()->create(['role' => UserRole::Owner]);
        $otherBiz = $this->provisionTenant(['owner_user_id' => $otherOwner->id]);

        Tenancy::actingAs($otherBiz->id, function () use ($otherBiz) {
            AdConnection::forceCreate([
                'business_id' => $otherBiz->id,
                'platform' => 'google',
                'account_id' => '111-222-3333',
                'is_connected' => true,
            ]);
        });

        Tenancy::actingAs($biz->id, function () use ($biz) {
            AdConnection::forceCreate([
                'business_id' => $biz->id,
                'platform' => 'meta',
                'account_id' => '444555666',
                'is_connected' => true,
            ]);
            AdConnection::forceCreate([
                'business_id' => $biz->id,
                'platform' => 'meta',
                'account_id' => '777888999',
                'is_connected' => false,
            ]);
        });

        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        Livewire::test(AdaccountConnectCard::class, ['businessId' => $biz->id])
            ->assertSee('meta: 444555666 (Connected)')
            ->assertSee('meta: 777888999 (Disconnected)')
            ->assertDontSee('google: 111-222-3333');
    }
}
