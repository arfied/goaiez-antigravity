<?php

namespace Tests\Modules\X175\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class CustomerfacingNoneScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-175.customerfacing-none'))->assertOk();

        Livewire::test(\App\Modules\X175\Ui\CustomerfacingNone::class)->assertOk();
    }
}
