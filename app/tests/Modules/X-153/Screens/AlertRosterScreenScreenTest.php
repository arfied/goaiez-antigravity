<?php

declare(strict_types=1);

namespace Tests\Modules\X153\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X153\Ui\AlertRosterScreen;
use Livewire\Livewire;
use Tests\TestCase;

class AlertRosterScreenScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-153.alert-roster-screen'))->assertOk();

        Livewire::test(AlertRosterScreen::class)->assertOk();
    }
}
