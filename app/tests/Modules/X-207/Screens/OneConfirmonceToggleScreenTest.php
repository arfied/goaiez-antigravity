<?php

namespace Tests\Modules\X207\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class OneConfirmonceToggleScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-207.one-confirmonce-toggle'))->assertOk();

        Livewire::test(\App\Modules\X207\Ui\OneConfirmonceToggle::class)->assertOk();
    }
}
