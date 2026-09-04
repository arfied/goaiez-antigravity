<?php

namespace Tests\Modules\X179\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class MatchScoresScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-179.match-scores'))->assertOk();

        Livewire::test(\App\Modules\X179\Ui\MatchScores::class)->assertOk();
    }
}
