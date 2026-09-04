<?php

declare(strict_types=1);

namespace Tests\Modules\X102\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X102\Ui\RageclickRate;
use Livewire\Livewire;
use Tests\TestCase;

class RageclickRateScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-102.rageclick-rate'))->assertOk();

        Livewire::test(RageclickRate::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-102.rageclick-rate.admin'))->assertOk();

        Livewire::test(RageclickRate::class)->assertOk();
    }
}
