<?php

namespace Tests\Modules\X149\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class QualityBoardScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-149.quality-board'))->assertOk();

        Livewire::test(\App\Modules\X149\Ui\QualityBoard::class)->assertOk();
    }
}
