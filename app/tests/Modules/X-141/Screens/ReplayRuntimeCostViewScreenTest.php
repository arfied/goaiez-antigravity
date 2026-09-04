<?php

declare(strict_types=1);

namespace Tests\Modules\X141\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X141\Ui\ReplayRuntimeCostView;
use Livewire\Livewire;
use Tests\TestCase;

class ReplayRuntimeCostViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-141.replay-runtime-cost'))->assertOk();

        Livewire::test(ReplayRuntimeCostView::class)->assertOk();
    }
}
