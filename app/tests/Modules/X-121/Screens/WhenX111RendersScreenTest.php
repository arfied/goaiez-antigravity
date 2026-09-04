<?php

namespace Tests\Modules\X121\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class WhenX111RendersScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-121.when-x111-renders'))->assertOk();

        Livewire::test(\App\Modules\X121\Ui\WhenX111Renders::class)->assertOk();
    }
}
