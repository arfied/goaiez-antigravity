<?php

declare(strict_types=1);

namespace Tests\Modules\X102\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X102\Ui\Thread;
use Livewire\Livewire;
use Tests\TestCase;

class ThreadScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-102.thread'))->assertOk();

        Livewire::test(Thread::class)->assertOk();
    }
}
