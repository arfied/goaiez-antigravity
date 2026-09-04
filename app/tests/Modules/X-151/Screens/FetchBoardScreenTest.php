<?php

namespace Tests\Modules\X151\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class FetchBoardScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-151.fetch-board'))->assertOk();

        Livewire::test(\App\Modules\X151\Ui\FetchBoard::class)->assertOk();
    }
}
