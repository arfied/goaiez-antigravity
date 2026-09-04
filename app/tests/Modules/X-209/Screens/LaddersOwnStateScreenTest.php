<?php

namespace Tests\Modules\X209\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class LaddersOwnStateScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-209.ladders-own-state'))->assertOk();

        Livewire::test(\App\Modules\X209\Ui\LaddersOwnState::class)->assertOk();
    }
}
