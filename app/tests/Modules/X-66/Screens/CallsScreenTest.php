<?php

declare(strict_types=1);

namespace Tests\Modules\X66\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X66\Ui\Calls;
use Livewire\Livewire;
use Tests\TestCase;

class CallsScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-66.calls'))->assertOk();

        Livewire::test(Calls::class)->assertOk();
    }
}
