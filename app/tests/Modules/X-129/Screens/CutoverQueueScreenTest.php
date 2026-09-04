<?php

declare(strict_types=1);

namespace Tests\Modules\X129\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X129\Ui\CutoverQueue;
use Livewire\Livewire;
use Tests\TestCase;

class CutoverQueueScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-129.cutover-queue'))->assertOk();

        Livewire::test(CutoverQueue::class)->assertOk();
    }
}
