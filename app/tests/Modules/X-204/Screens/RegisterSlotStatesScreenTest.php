<?php

declare(strict_types=1);

namespace Tests\Modules\X204\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X204\Ui\RegisterSlotStates;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterSlotStatesScreenTest extends TestCase
{
    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);
        \App\Support\Tenancy::set($biz->id);

        $this->get(route('x-204.register-slot-states.admin'))->assertOk();

        Livewire::test(RegisterSlotStates::class)->assertOk();
    }
}
