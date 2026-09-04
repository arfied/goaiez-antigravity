<?php

declare(strict_types=1);

namespace Tests\Modules\X111\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X111\Ui\Console;
use Livewire\Livewire;
use Tests\TestCase;

class ConsoleScreenTest extends TestCase
{
    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-111.console.admin'))->assertOk();

        Livewire::test(Console::class)->assertOk();
    }
}
