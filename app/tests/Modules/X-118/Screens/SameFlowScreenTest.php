<?php

declare(strict_types=1);

namespace Tests\Modules\X118\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class SameFlowScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-118.same-flow'))->assertOk();

        Livewire::test(\App\Modules\X118\Ui\SameFlow::class)->assertOk();
    }
}
