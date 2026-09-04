<?php

declare(strict_types=1);

namespace Tests\Modules\X149\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X149\Ui\QualityBoard;
use Livewire\Livewire;
use Tests\TestCase;

class QualityBoardScreenTest extends TestCase
{
    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-149.quality-board.admin'))->assertOk();

        Livewire::test(QualityBoard::class)->assertOk();
    }
}
