<?php

namespace Tests\Modules\X185\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ExperimentBoardScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-185.experiment-board'))->assertOk();

        Livewire::test(\App\Modules\X185\Ui\ExperimentBoard::class)->assertOk();
    }
}
