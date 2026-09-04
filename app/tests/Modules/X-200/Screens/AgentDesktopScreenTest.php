<?php

declare(strict_types=1);

namespace Tests\Modules\X200\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X200\Ui\AgentDesktop;
use Livewire\Livewire;
use Tests\TestCase;

class AgentDesktopScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-200.agent-desktop'))->assertOk();

        Livewire::test(AgentDesktop::class)->assertOk();
    }
}
