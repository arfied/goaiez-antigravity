<?php

declare(strict_types=1);

namespace Tests\Modules\X124\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X124\Ui\TodaysRecommendationStrip;
use Livewire\Livewire;
use Tests\TestCase;

class TodaysRecommendationStripScreenTest extends TestCase
{
    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-124.todays-recommendation-strip.admin'))->assertOk();

        Livewire::test(TodaysRecommendationStrip::class)->assertOk();
    }
}
