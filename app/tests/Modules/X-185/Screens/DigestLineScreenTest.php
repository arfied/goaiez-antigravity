<?php

namespace Tests\Modules\X185\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class DigestLineScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-185.digest-line'))->assertOk();

        Livewire::test(\App\Modules\X185\Ui\DigestLine::class)->assertOk();
    }
}
