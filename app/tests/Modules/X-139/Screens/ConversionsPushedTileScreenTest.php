<?php

namespace Tests\Modules\X139\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ConversionsPushedTileScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-139.conversions-pushed-tile'))->assertOk();

        Livewire::test(\App\Modules\X139\Ui\ConversionsPushedTile::class)->assertOk();
    }
}
