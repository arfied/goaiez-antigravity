<?php

declare(strict_types=1);

namespace Tests\Modules\X109\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

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
