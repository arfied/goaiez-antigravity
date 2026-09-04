<?php

namespace Tests\Modules\X66\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class LatencyP50p95PerScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-66.latency-p50p95-per'))->assertOk();

        Livewire::test(\App\Modules\X66\Ui\LatencyP50p95Per::class)->assertOk();
    }
}
