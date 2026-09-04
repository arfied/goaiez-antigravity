<?php

declare(strict_types=1);

namespace Tests\Modules\X190\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

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
