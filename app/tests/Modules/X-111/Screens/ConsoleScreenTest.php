<?php

namespace Tests\Modules\X111\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ConsoleScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-111.console'))->assertOk();

        Livewire::test(\App\Modules\X111\Ui\Console::class)->assertOk();
    }
}
