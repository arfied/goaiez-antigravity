<?php

namespace Tests\Modules\X119\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class FactFreshnessPerScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-119.fact-freshness-per'))->assertOk();

        Livewire::test(\App\Modules\X119\Ui\FactFreshnessPer::class)->assertOk();
    }
}
