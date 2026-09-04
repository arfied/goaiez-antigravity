<?php

namespace Tests\Modules\X210\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class RedemptionsListScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-210.redemptions'))->assertOk();

        Livewire::test(\App\Modules\X210\Ui\RedemptionsList::class)->assertOk();
    }
}
