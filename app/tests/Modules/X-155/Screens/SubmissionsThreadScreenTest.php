<?php

declare(strict_types=1);

namespace Tests\Modules\X155\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X155\Ui\SubmissionsThread;
use Livewire\Livewire;
use Tests\TestCase;

class SubmissionsThreadScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-155.submissions-thread'))->assertOk();

        Livewire::test(SubmissionsThread::class)->assertOk();
    }
}
