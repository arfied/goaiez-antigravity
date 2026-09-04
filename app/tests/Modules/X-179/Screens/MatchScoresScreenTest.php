<?php

declare(strict_types=1);

namespace Tests\Modules\X179\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class MatchScoresScreenTest extends TestCase
{

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-179.match-scores.admin'))->assertOk();

        Livewire::test(\App\Modules\X179\Ui\MatchScores::class)->assertOk();
    }
}
