<?php

namespace Tests\Modules\X66\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class CallsScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-66.calls'))->assertOk();

        Livewire::test(\App\Modules\X66\Ui\Calls::class)->assertOk();
    }
}
