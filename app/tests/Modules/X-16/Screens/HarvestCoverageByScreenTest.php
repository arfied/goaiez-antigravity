<?php

namespace Tests\Modules\X16\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class HarvestCoverageByScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-16.harvest-coverage-by'))->assertOk();

        Livewire::test(\App\Modules\X16\Ui\HarvestCoverageBy::class)->assertOk();
    }
}
