<?php

declare(strict_types=1);

namespace Tests\Modules\X192\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X192\Ui\MembershipsList;
use Livewire\Livewire;
use Tests\TestCase;

class MembershipsListScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-192.memberships-list'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console');

        Livewire::test(MembershipsList::class)->assertOk();
    }
}
