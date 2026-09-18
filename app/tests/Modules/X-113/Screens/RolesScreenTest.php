<?php

declare(strict_types=1);

namespace Tests\Modules\X113\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X113\Models\Role;
use App\Modules\X113\Ui\Roles;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class RolesScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-113.roles'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No roles yet.');

        Tenancy::setUser($owner->id);
        Role::create([
            'business_id' => $biz->id,
            'name' => 'Distinctive role 4642',
            'description' => 'what this role may do',
        ]);
        Tenancy::forget();

        $this->get(route('x-113.roles'))
            ->assertSee('Distinctive role 4642')
            ->assertDontSee('No roles yet.');

        Livewire::test(Roles::class)->assertOk();
    }
}
