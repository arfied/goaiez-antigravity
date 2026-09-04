<?php

namespace Tests\Modules\X186\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class LiveRunScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-186.live-run'))->assertOk();

        Livewire::test(\App\Modules\X186\Ui\LiveRun::class)->assertOk();
    }
}
