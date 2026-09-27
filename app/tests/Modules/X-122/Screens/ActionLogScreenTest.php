<?php

declare(strict_types=1);

namespace Tests\Modules\X122\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X122\Models\ActionInvocation;
use App\Modules\X122\Ui\ActionLog;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ActionLogScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::setUser($owner->id);
        ActionInvocation::factory()->create([
            'business_id' => $biz->id,
            'action_name' => 'Demo Action',
            'status' => 'completed',
        ]);
        Tenancy::forget();

        $this->get(route('x-122.action-log'))
            ->assertOk()
            ->assertSee('Demo Action');

        Livewire::test(ActionLog::class)
            ->assertOk()
            ->assertSee('Demo Action');
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-122.action-log.admin'))
            ->assertOk()
            ->assertSeeText('No action invocations recorded.');

        Livewire::test(ActionLog::class)->assertOk();
    }
}
