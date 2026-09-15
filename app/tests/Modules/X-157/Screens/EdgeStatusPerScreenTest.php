<?php

declare(strict_types=1);

namespace Tests\Modules\X157\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X157\Ui\EdgeStatusPer;
use App\Modules\X157\Models\Deployment;
use App\Modules\X157\Models\EdgeZone;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class EdgeStatusPerScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-157.edge-status-per'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No active edge deployments.');

        Tenancy::setUser($owner->id);
        $zone = EdgeZone::create(['business_id' => $biz->id, 'domain_name' => 'distinctive-4486.example', 'zone_id' => 'zone_distinctive_4486', 'has_valid_ssl' => true]);
        Deployment::create(['business_id' => $biz->id, 'edge_zone_id' => $zone->id, 'deploy_hash' => 'hash_distinctive_4486', 'status' => 'deployed', 'deployed_at' => now()]);
        Tenancy::forget();

        $this->get(route('x-157.edge-status-per'))
            ->assertOk()
            ->assertSee('hash_distinctive_4486')
            ->assertSee('distinctive-4486.example')
            ->assertSee('SSL Active')
            ->assertDontSee('No active edge deployments.');

        Livewire::test(EdgeStatusPer::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-157.edge-status-per.admin'))->assertOk();

        Livewire::test(EdgeStatusPer::class)->assertOk();
    }
}
