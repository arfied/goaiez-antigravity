<?php

declare(strict_types=1);

namespace Tests\Modules\X105\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class PipelineBoardScreenTest extends TestCase
{

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-105.pipeline-board.admin'))->assertOk();

        Livewire::test(\App\Modules\X105\Ui\PipelineBoard::class)->assertOk();
    }
}
