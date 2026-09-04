<?php

declare(strict_types=1);

namespace Tests\Modules\X118\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class TtfmDistributionScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-118.ttfm-distribution'))->assertOk();

        Livewire::test(\App\Modules\X118\Ui\TtfmDistribution::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-118.ttfm-distribution.admin'))->assertOk();

        Livewire::test(\App\Modules\X118\Ui\TtfmDistribution::class)->assertOk();
    }
}
