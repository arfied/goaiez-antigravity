<?php

namespace Tests\Modules\X124\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class TodaysRecommendationStripScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-124.todays-recommendation-strip'))->assertOk();

        Livewire::test(\App\Modules\X124\Ui\TodaysRecommendationStrip::class)->assertOk();
    }
}
