<?php

declare(strict_types=1);

namespace Tests\Modules\X137\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X137\Ui\DniPoolUtilisation;
use Livewire\Livewire;
use Tests\TestCase;

class DniPoolUtilisationScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-137.dni-pool-utilisation'))->assertOk();

        Livewire::test(DniPoolUtilisation::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-137.dni-pool-utilisation.admin'))->assertOk();

        Livewire::test(DniPoolUtilisation::class)->assertOk();
    }
}
