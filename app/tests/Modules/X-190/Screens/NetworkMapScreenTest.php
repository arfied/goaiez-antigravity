<?php

namespace Tests\Modules\X190\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class NetworkMapScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-190.network-map'))->assertOk();

        Livewire::test(\App\Modules\X190\Ui\NetworkMap::class)->assertOk();
    }
}
