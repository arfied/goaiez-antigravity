<?php

declare(strict_types=1);

namespace Tests\Modules\X177\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X177\Ui\GbpCard;
use Livewire\Livewire;
use Tests\TestCase;

class GbpCardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-177.gbp-card'))->assertOk();

        Livewire::test(GbpCard::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-177.gbp-card.admin'))->assertOk();

        Livewire::test(GbpCard::class)->assertOk();
    }
}
