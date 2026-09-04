<?php

namespace Tests\Modules\X202\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class MobileScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-202.mobile'))->assertOk();

        Livewire::test(\App\Modules\X202\Ui\Mobile::class)->assertOk();
    }
}
