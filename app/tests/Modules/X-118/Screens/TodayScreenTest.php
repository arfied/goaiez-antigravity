<?php

declare(strict_types=1);

namespace Tests\Modules\X118\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X118\Ui\Today;
use Livewire\Livewire;
use Tests\TestCase;

class TodayScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-118.today'))->assertOk();

        Livewire::test(Today::class)->assertOk();
    }
}
