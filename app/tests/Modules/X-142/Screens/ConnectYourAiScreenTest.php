<?php

namespace Tests\Modules\X142\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ConnectYourAiScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-142.connect-your-ai'))->assertOk();

        Livewire::test(\App\Modules\X142\Ui\ConnectYourAi::class)->assertOk();
    }
}
