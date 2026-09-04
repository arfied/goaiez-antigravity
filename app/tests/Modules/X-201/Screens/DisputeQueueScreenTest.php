<?php

namespace Tests\Modules\X201\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class DisputeQueueScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-201.dispute-queue'))->assertOk();

        Livewire::test(\App\Modules\X201\Ui\DisputeQueue::class)->assertOk();
    }
}
