<?php

namespace Tests\Modules\X10\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class UnassignedCountScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-10.unassigned-count'))->assertOk();

        Livewire::test(\App\Modules\X10\Ui\UnassignedCount::class)->assertOk();
    }
}
