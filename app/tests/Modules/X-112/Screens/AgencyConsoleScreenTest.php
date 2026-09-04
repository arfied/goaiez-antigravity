<?php

namespace Tests\Modules\X112\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class AgencyConsoleScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-112.agency-console'))->assertOk();

        Livewire::test(\App\Modules\X112\Ui\AgencyConsole::class)->assertOk();
    }
}
