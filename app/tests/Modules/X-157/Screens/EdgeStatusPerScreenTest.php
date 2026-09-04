<?php

namespace Tests\Modules\X157\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class EdgeStatusPerScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-157.edge-status-per'))->assertOk();

        Livewire::test(\App\Modules\X157\Ui\EdgeStatusPer::class)->assertOk();
    }
}
