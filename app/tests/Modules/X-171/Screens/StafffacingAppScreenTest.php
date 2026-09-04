<?php

namespace Tests\Modules\X171\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class StafffacingAppScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-171.stafffacing-app'))->assertOk();

        Livewire::test(\App\Modules\X171\Ui\StafffacingApp::class)->assertOk();
    }
}
