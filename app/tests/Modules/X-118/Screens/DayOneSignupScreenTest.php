<?php

namespace Tests\Modules\X118\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class DayOneSignupScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-118.day-one-signup'))->assertOk();

        Livewire::test(\App\Modules\X118\Ui\DayOneSignup::class)->assertOk();
    }
}
