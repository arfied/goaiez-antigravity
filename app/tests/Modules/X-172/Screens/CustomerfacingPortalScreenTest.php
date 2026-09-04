<?php

namespace Tests\Modules\X172\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class CustomerfacingPortalScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-172.customerfacing-portal'))->assertOk();

        Livewire::test(\App\Modules\X172\Ui\CustomerfacingPortal::class)->assertOk();
    }
}
