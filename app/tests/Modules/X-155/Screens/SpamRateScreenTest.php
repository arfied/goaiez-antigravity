<?php

declare(strict_types=1);

namespace Tests\Modules\X155\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X155\Ui\SpamRate;
use Livewire\Livewire;
use Tests\TestCase;

class SpamRateScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-155.spam-rate'))->assertOk();

        Livewire::test(SpamRate::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-155.spam-rate.admin'))->assertOk();

        Livewire::test(SpamRate::class)->assertOk();
    }
}
