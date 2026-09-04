<?php

namespace Tests\Modules\X155\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class SubmissionsThreadScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-155.submissions-thread'))->assertOk();

        Livewire::test(\App\Modules\X155\Ui\SubmissionsThread::class)->assertOk();
    }
}
