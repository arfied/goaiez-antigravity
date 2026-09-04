<?php

declare(strict_types=1);

namespace Tests\Modules\X118\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X118\Ui\Groundcheck;
use Livewire\Livewire;
use Tests\TestCase;

class GroundcheckScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-118.groundcheck'))->assertOk();

        Livewire::test(Groundcheck::class)->assertOk();
    }
}
