<?php

namespace Tests\Modules\X109\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ManualQueueScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-109.manual-queue'))->assertOk();

        Livewire::test(\App\Modules\X109\Ui\ManualQueue::class)->assertOk();
    }
}
