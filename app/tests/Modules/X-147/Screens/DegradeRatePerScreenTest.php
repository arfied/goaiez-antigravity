<?php

namespace Tests\Modules\X147\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class DegradeRatePerScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-147.degrade-rate-per'))->assertOk();

        Livewire::test(\App\Modules\X147\Ui\DegradeRatePer::class)->assertOk();
    }
}
