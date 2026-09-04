<?php

declare(strict_types=1);

namespace Tests\Modules\X197\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

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
