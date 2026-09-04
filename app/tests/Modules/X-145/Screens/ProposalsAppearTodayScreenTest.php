<?php

namespace Tests\Modules\X145\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ProposalsAppearTodayScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-145.proposals-appear-today'))->assertOk();

        Livewire::test(\App\Modules\X145\Ui\ProposalsAppearToday::class)->assertOk();
    }
}
