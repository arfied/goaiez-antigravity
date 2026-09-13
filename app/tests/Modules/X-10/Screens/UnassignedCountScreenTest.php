<?php

declare(strict_types=1);

namespace Tests\Modules\X10\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X10\Ui\UnassignedCount;
use Livewire\Livewire;
use Tests\TestCase;

class UnassignedCountScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-10.unassigned-count'))
            ->assertOk()
            ->assertSee('Unassigned Leads Queue')
            ->assertSee('No unassigned leads in the queue.');

        Livewire::test(UnassignedCount::class, ['businessId' => $biz->id])->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-10.unassigned-count.admin'))->assertOk();

        Livewire::test(UnassignedCount::class, ['businessId' => $biz->id])->assertOk();
    }
}
