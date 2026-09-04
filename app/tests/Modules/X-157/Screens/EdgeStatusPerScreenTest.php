<?php

declare(strict_types=1);

namespace Tests\Modules\X157\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X157\Ui\EdgeStatusPer;
use Livewire\Livewire;
use Tests\TestCase;

class EdgeStatusPerScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-157.edge-status-per'))->assertOk();

        Livewire::test(EdgeStatusPer::class)->assertOk();
    }
}
