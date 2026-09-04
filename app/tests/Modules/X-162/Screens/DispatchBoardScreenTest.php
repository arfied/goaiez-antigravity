<?php

namespace Tests\Modules\X162\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class DispatchBoardScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-162.dispatch-board'))->assertOk();

        Livewire::test(\App\Modules\X162\Ui\DispatchBoard::class)->assertOk();
    }
}
