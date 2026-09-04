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
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-177.suspensionrisk-events-fleetwide'))->assertOk();

        Livewire::test(SuspensionriskEventsFleetwide::class)->assertOk();
    }
}
