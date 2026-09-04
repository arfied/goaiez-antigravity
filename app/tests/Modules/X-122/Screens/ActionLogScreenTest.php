<?php

declare(strict_types=1);

namespace Tests\Modules\X122\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

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
