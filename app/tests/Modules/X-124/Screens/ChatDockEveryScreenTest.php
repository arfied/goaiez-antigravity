<?php

declare(strict_types=1);

namespace Tests\Modules\X124\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X124\Ui\ChatDockEvery;
use Livewire\Livewire;
use Tests\TestCase;

class ChatDockEveryScreenTest extends TestCase
{
    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-124.chat-dock-every.admin'))->assertOk();

        Livewire::test(ChatDockEvery::class)->assertOk();
    }
}
