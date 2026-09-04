<?php

declare(strict_types=1);

namespace Tests\Modules\X125\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

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
