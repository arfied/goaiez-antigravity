<?php

declare(strict_types=1);

namespace Tests\Modules\X171\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X171\Ui\StafffacingApp;
use Livewire\Livewire;
use Tests\TestCase;

class StafffacingAppScreenTest extends TestCase
{
    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-171.stafffacing-app.admin'))->assertOk();

        Livewire::test(StafffacingApp::class)->assertOk();
    }
    public function test_the_device_id_cannot_be_overwritten_from_the_browser(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        Livewire::test(StafffacingApp::class)->set('deviceId', 'other-device-4852');
    }
}
