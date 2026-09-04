<?php

namespace Tests\Modules\X122\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ActionLogScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-122.action-log'))->assertOk();

        Livewire::test(\App\Modules\X122\Ui\ActionLog::class)->assertOk();
    }
}
