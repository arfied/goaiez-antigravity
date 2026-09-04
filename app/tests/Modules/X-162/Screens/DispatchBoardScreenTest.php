<?php

declare(strict_types=1);

namespace Tests\Modules\X162\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X162\Ui\DispatchBoard;
use Livewire\Livewire;
use Tests\TestCase;

class DispatchBoardScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-162.dispatch-board'))->assertOk();

        Livewire::test(DispatchBoard::class)->assertOk();
    }
}
