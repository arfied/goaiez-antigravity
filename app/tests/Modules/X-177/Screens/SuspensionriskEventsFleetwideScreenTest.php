<?php

namespace Tests\Modules\X177\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class SuspensionriskEventsFleetwideScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-177.suspensionrisk-events-fleetwide'))->assertOk();

        Livewire::test(\App\Modules\X177\Ui\SuspensionriskEventsFleetwide::class)->assertOk();
    }
}
