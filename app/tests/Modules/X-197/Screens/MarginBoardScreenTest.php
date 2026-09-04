<?php

namespace Tests\Modules\X197\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class MarginBoardScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-197.margin-board'))->assertOk();

        Livewire::test(\App\Modules\X197\Ui\MarginBoard::class)->assertOk();
    }
}
