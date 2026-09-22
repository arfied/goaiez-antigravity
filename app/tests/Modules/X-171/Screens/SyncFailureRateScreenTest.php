<?php

declare(strict_types=1);

namespace Tests\Modules\X171\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X171\Ui\SyncFailureRate;
use Livewire\Livewire;
use Tests\TestCase;

class SyncFailureRateScreenTest extends TestCase
{
    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-171.sync-failure-rate.admin'))
            ->assertOk()
            ->assertSeeText('No sync conflicts. Every device mutation replayed cleanly.');

        Livewire::test(SyncFailureRate::class)->assertOk();
    }
}
