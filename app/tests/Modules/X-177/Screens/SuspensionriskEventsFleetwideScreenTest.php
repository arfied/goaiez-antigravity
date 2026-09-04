<?php

declare(strict_types=1);

namespace Tests\Modules\X177\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X177\Ui\SuspensionriskEventsFleetwide;
use Livewire\Livewire;
use Tests\TestCase;

class SuspensionriskEventsFleetwideScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-177.suspensionrisk-events-fleetwide'))->assertOk();

        Livewire::test(SuspensionriskEventsFleetwide::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-177.suspensionrisk-events-fleetwide.admin'))->assertOk();

        Livewire::test(SuspensionriskEventsFleetwide::class)->assertOk();
    }
}
