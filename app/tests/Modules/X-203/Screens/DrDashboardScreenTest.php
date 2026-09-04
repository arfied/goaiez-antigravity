<?php

namespace Tests\Modules\X203\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class DrDashboardScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-203.dr-dashboard'))->assertOk();

        Livewire::test(\App\Modules\X203\Ui\DrDashboard::class)->assertOk();
    }
}
