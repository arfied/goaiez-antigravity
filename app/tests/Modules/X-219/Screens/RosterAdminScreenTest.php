<?php

declare(strict_types=1);

namespace Tests\Modules\X219\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X219\Ui\RosterAdmin;
use Livewire\Livewire;
use Tests\TestCase;

class RosterAdminScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-219.roster-admin'))->assertOk();

        Livewire::test(RosterAdmin::class)->assertOk();
    }
}
