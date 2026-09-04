<?php

declare(strict_types=1);

namespace Tests\Modules\X200\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class WallboardScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-200.wallboard'))->assertOk();

        Livewire::test(\App\Modules\X200\Ui\Wallboard::class)->assertOk();
    }
}
