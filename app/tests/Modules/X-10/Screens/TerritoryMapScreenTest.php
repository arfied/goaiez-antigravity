<?php

namespace Tests\Modules\X10\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class TerritoryMapScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-10.territory-map'))->assertOk();

        Livewire::test(\App\Modules\X10\Ui\TerritoryMap::class)->assertOk();
    }
}
