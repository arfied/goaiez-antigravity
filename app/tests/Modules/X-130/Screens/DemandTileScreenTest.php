<?php

namespace Tests\Modules\X130\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class DemandTileScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-130.demand-tile'))->assertOk();

        Livewire::test(\App\Modules\X130\Ui\DemandTile::class)->assertOk();
    }
}
