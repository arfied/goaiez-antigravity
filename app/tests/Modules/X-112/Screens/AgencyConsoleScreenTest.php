<?php

declare(strict_types=1);

namespace Tests\Modules\X112\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X112\Ui\AgencyConsole;
use Livewire\Livewire;
use Tests\TestCase;

class AgencyConsoleScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-112.agency-console'))->assertOk();

        Livewire::test(AgencyConsole::class)->assertOk();
    }
}
