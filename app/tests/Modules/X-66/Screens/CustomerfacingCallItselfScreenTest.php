<?php

declare(strict_types=1);

namespace Tests\Modules\X66\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerfacingCallItselfScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-66.customerfacing-call-itself'))->assertOk();

        Livewire::test(\App\Modules\X66\Ui\CustomerfacingCallItself::class)->assertOk();
    }
}
