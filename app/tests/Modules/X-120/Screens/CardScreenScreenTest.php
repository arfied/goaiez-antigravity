<?php

namespace Tests\Modules\X120\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class CardScreenScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-120.card-screen'))->assertOk();

        Livewire::test(\App\Modules\X120\Ui\CardScreen::class)->assertOk();
    }
}
