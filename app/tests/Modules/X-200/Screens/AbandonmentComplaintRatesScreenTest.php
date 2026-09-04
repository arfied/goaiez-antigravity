<?php

namespace Tests\Modules\X200\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class AbandonmentComplaintRatesScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-200.abandonment-complaint-rates'))->assertOk();

        Livewire::test(\App\Modules\X200\Ui\AbandonmentComplaintRates::class)->assertOk();
    }
}
