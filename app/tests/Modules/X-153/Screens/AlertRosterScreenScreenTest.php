<?php

namespace Tests\Modules\X153\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class AlertRosterScreenScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-153.alert-roster-screen'))->assertOk();

        Livewire::test(\App\Modules\X153\Ui\AlertRosterScreen::class)->assertOk();
    }
}
