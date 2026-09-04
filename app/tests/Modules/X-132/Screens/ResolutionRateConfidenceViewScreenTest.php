<?php

declare(strict_types=1);

namespace Tests\Modules\X132\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X132\Ui\ResolutionRateConfidenceView;
use Livewire\Livewire;
use Tests\TestCase;

class ResolutionRateConfidenceViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-132.resolution-rate-confidence'))->assertOk();

        Livewire::test(ResolutionRateConfidenceView::class)->assertOk();
    }
}
