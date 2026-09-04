<?php

namespace Tests\Modules\X137\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class DniPoolUtilisationScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-137.dni-pool-utilisation'))->assertOk();

        Livewire::test(\App\Modules\X137\Ui\DniPoolUtilisation::class)->assertOk();
    }
}
