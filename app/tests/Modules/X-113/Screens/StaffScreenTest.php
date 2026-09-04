<?php

namespace Tests\Modules\X113\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class StaffScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-113.staff'))->assertOk();

        Livewire::test(\App\Modules\X113\Ui\Staff::class)->assertOk();
    }
}
