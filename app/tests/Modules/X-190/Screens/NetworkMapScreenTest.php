<?php

declare(strict_types=1);

namespace Tests\Modules\X190\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X190\Models\PartnerPool;
use App\Modules\X190\Ui\NetworkMap;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class NetworkMapScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-190.network-map'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No partners in your network yet.');

        Tenancy::set((int) $biz->id);
        PartnerPool::create(['business_id' => $biz->id, 'company_name' => 'Distinctive Partner Co 4471', 'category' => 'plumbing', 'territory_zip' => '75002']);
        Tenancy::forget();

        $this->get(route('x-190.network-map'))
            ->assertOk()
            ->assertSee('Distinctive Partner Co 4471')
            ->assertSee('75002')
            ->assertDontSee('No partners in your network yet.');

        Livewire::actingAs($owner)->test(NetworkMap::class, ['businessId' => $biz->id])->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-190.network-map.admin'))->assertOk();

        Livewire::test(NetworkMap::class)->assertOk();
    }
}
