<?php

declare(strict_types=1);

namespace Tests\Modules\X185\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

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
