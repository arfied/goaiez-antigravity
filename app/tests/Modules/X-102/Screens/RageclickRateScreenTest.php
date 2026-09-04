<?php

namespace Tests\Modules\X102\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class RageclickRateScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-102.rageclick-rate'))->assertOk();

        Livewire::test(\App\Modules\X102\Ui\RageclickRate::class)->assertOk();
    }
}
