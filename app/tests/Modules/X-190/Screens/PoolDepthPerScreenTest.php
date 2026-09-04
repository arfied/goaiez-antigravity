<?php

declare(strict_types=1);

namespace Tests\Modules\X190\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class PoolDepthPerScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-190.pool-depth-per'))->assertOk();

        Livewire::test(\App\Modules\X190\Ui\PoolDepthPer::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-190.pool-depth-per.admin'))->assertOk();

        Livewire::test(\App\Modules\X190\Ui\PoolDepthPer::class)->assertOk();
    }
}
