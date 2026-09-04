<?php

declare(strict_types=1);

namespace Tests\Modules\X66\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X66\Ui\LatencyP50p95Per;
use Livewire\Livewire;
use Tests\TestCase;

class LatencyP50p95PerScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-66.latency-p50p95-per'))->assertOk();

        Livewire::test(LatencyP50p95Per::class)->assertOk();
    }
}
