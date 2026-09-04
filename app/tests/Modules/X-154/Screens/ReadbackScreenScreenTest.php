<?php

namespace Tests\Modules\X154\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ReadbackScreenScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-154.readback-screen'))->assertOk();

        Livewire::test(\App\Modules\X154\Ui\ReadbackScreen::class)->assertOk();
    }
}
