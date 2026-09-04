<?php

namespace Tests\Modules\X125\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class RunsScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-125.runs'))->assertOk();

        Livewire::test(\App\Modules\X125\Ui\Runs::class)->assertOk();
    }
}
