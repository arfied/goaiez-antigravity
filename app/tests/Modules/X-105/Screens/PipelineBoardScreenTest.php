<?php

namespace Tests\Modules\X105\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class PipelineBoardScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-105.pipeline-board'))->assertOk();

        Livewire::test(\App\Modules\X105\Ui\PipelineBoard::class)->assertOk();
    }
}
