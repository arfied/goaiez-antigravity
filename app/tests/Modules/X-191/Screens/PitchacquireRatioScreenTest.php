<?php

declare(strict_types=1);

namespace Tests\Modules\X191\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class PitchacquireRatioScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-191.pitchacquire-ratio'))->assertOk();

        Livewire::test(\App\Modules\X191\Ui\PitchacquireRatio::class)->assertOk();
    }
}
