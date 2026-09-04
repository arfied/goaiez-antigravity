<?php

namespace Tests\Modules\X132\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ResolutionRateConfidenceViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-132.resolution-rate-confidence'))->assertOk();

        Livewire::test(\App\Modules\X132\Ui\ResolutionRateConfidenceView::class)->assertOk();
    }
}
