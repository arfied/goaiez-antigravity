<?php

declare(strict_types=1);

namespace Tests\Modules\X151\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X151\Ui\FetchBoard;
use Livewire\Livewire;
use Tests\TestCase;

class FetchBoardScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-151.fetch-board'))->assertOk();

        Livewire::test(FetchBoard::class)->assertOk();
    }
}
